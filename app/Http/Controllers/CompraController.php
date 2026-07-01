<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU3 Compras (modulo "compras"): registrar una compra MULTI-PRODUCTO a un proveedor. Por cada
 * linea sube el stock y anota un movimiento de inventario (ingreso, motivo "compra");
 * monto_total = Σ subtotales. Todo dentro de una transaccion via Inventario::registrarMovimiento.
 *
 * NO hay edit/update: para corregir se ANULA y se re-registra (igual que Ventas). ANULAR revierte
 * el stock (movimiento de correccion/salida) y marca la compra como "anulada" (conserva historial),
 * solo si el stock actual alcanza para revertir cada linea.
 */
class CompraController extends Controller
{
    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $compras = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Compra $c) => [
                'id' => $c->id,
                'fecha' => $c->fecha_compra?->format('d/m/Y'),
                'proveedor' => $c->proveedor?->name,
                'items' => $c->detalles_count,
                'monto_total' => $c->monto_total,
                'estado' => $c->estado,
            ]);

        return Inertia::render('compras/Index', [
            'compras' => $compras,
            'filtros' => $filtros,
            'proveedores' => User::conRolVigente('Proveedor')->orderBy('name')->get(['id', 'name']),
            'puedeCrear' => $request->user()->tienePermiso('compras', 'registrar'),
            'puedeEliminar' => $request->user()->tienePermiso('compras', 'eliminar'),
            'puedeReportar' => $request->user()->tienePermiso('compras', 'reportar'),
        ]);
    }

    /**
     * Genera el reporte de la lista FILTRADA en PDF o CSV (Excel). Reusa los mismos filtros que el
     * index. Contenido: una fila por compra + total. Ruta protegida con permiso:compras,listar.
     */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $compras = $query->get();

        $columnas = ['Fecha', 'Proveedor', 'Ítems', 'Total (Bs)', 'Estado'];
        $filas = $compras->map(fn (Compra $c) => [
            $c->fecha_compra?->format('d/m/Y'),
            $c->proveedor?->name,
            $c->detalles_count,
            number_format((float) $c->monto_total, 2, '.', ''),
            $c->estado === Compra::ESTADO_ANULADA ? 'Anulada' : 'Registrada',
        ])->all();
        $sumaTotal = number_format((float) $compras->sum('monto_total'), 2, '.', '');

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Compras',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['TOTAL', '', '', $sumaTotal, ''],
        ], 'compras');
    }

    /**
     * Construye la consulta de compras aplicando los filtros del request (proveedor/estado/fechas).
     * La comparten index() (pagina) y reporte() (->get()).
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'proveedor_id' => $request->integer('proveedor_id') ?: null,
            'estado' => $request->string('estado')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $query = Compra::query()
            ->with('proveedor:id,name')
            ->withCount('detalles')
            ->when($filtros['proveedor_id'], fn ($q, $id) => $q->where('proveedor_id', $id))
            ->when($filtros['estado'], fn ($q, $e) => $q->where('estado', $e))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_compra', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_compra', '<=', $h))
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['proveedor_id']) {
            $partes[] = 'Proveedor: '.(User::find($f['proveedor_id'])?->name ?? $f['proveedor_id']);
        }
        if ($f['estado']) {
            $partes[] = 'Estado: '.ucfirst($f['estado']);
        }
        if ($f['desde']) {
            $partes[] = 'Desde: '.$f['desde'];
        }
        if ($f['hasta']) {
            $partes[] = 'Hasta: '.$f['hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Sin filtros (todas las compras)';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    public function create(): Response
    {
        return Inertia::render('compras/Form', [
            'proveedores' => User::conRolVigente('Proveedor')->orderBy('name')->get(['id', 'name']),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'precio', 'stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($datos) {
            $compra = Compra::create([
                'proveedor_id' => $datos['proveedor_id'],
                'fecha_compra' => $datos['fecha_compra'],
                'monto_total' => 0, // se fija abajo con la suma real
                'estado' => Compra::ESTADO_REGISTRADA,
            ]);

            $total = 0;
            foreach ($datos['lineas'] as $linea) {
                $producto = Producto::findOrFail($linea['producto_id']);
                $subtotal = round($linea['cantidad'] * $linea['precio_unitario'], 2);
                $total += $subtotal;

                $compra->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $subtotal,
                ]);

                // La compra ingresa stock (motivo compra).
                Inventario::registrarMovimiento(
                    $producto,
                    Inventario::INGRESO,
                    $linea['cantidad'],
                    Inventario::MOTIVO_COMPRA,
                    $datos['fecha_compra'],
                );
            }

            $compra->update(['monto_total' => $total]);

            Bitacora::registrar(
                'crear',
                "Registró la compra #{$compra->id} (Bs {$total})",
                'compras',
            );
        });

        $this->toastExito('Compra registrada.');

        return redirect()->route('compras.index');
    }

    public function show(Compra $compra): Response
    {
        $compra->load('proveedor:id,name', 'detalles.producto:id,nombre');

        return Inertia::render('compras/Show', [
            'compra' => [
                'id' => $compra->id,
                'fecha' => $compra->fecha_compra?->format('d/m/Y'),
                'proveedor' => $compra->proveedor?->name,
                'monto_total' => $compra->monto_total,
                'estado' => $compra->estado,
                'detalles' => $compra->detalles->map(fn ($d) => [
                    'producto' => $d->producto?->nombre,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal' => $d->subtotal,
                ]),
            ],
        ]);
    }

    /**
     * ANULAR: revierte el stock de cada linea (movimiento de correccion/salida) y marca la compra
     * como anulada. Solo si el stock actual alcanza para revertir TODAS las lineas (puede haberse
     * vendido parte). No borra la compra (conserva historial).
     */
    public function destroy(Compra $compra): RedirectResponse
    {
        abort_if($compra->estado === Compra::ESTADO_ANULADA, 422, 'La compra ya está anulada.');

        $compra->load('detalles.producto');

        // Verificar que el stock alcanza para revertir cada linea antes de tocar nada.
        foreach ($compra->detalles as $detalle) {
            if ($detalle->producto->stock < $detalle->cantidad) {
                $this->toastError(
                    "No se puede anular: el stock de «{$detalle->producto->nombre}» "
                    ."({$detalle->producto->stock}) es menor a las {$detalle->cantidad} unidades a revertir.",
                );

                return back();
            }
        }

        DB::transaction(function () use ($compra) {
            foreach ($compra->detalles as $detalle) {
                Inventario::registrarMovimiento(
                    $detalle->producto,
                    Inventario::SALIDA,
                    $detalle->cantidad,
                    Inventario::MOTIVO_CORRECCION,
                );
            }

            $compra->update(['estado' => Compra::ESTADO_ANULADA]);

            Bitacora::registrar('eliminar', "Anuló la compra #{$compra->id}", 'compras');
        });

        $this->toastExito('Compra anulada y stock revertido.');

        return redirect()->route('compras.index');
    }

    /**
     * Reglas de validacion (en espanol via lang/es). El proveedor debe ser un usuario con rol
     * Proveedor VIGENTE. Las lineas no pueden repetir producto (PK compuesta del detalle).
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:users,id'],
            'fecha_compra' => ['required', 'date'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('producto', 'id')->where('activo', true),
            ],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], [
            'lineas.required' => 'Agrega al menos un producto a la compra.',
            'lineas.*.producto_id.distinct' => 'No repitas el mismo producto en dos líneas.',
        ], [
            'proveedor_id' => 'proveedor',
            'fecha_compra' => 'fecha',
        ]);

        // El proveedor debe tener el rol Proveedor vigente (no cualquier usuario).
        $esProveedor = User::conRolVigente('Proveedor')->whereKey($datos['proveedor_id'])->exists();
        if (! $esProveedor) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El usuario seleccionado no es un proveedor vigente.',
            ]);
        }

        return $datos;
    }
}
