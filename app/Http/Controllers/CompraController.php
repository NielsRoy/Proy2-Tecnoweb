<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
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
        $compras = Compra::query()
            ->with('proveedor:id,name')
            ->withCount('detalles')
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
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
            'puedeCrear' => $request->user()->tienePermiso('compras', 'registrar'),
            'puedeEliminar' => $request->user()->tienePermiso('compras', 'eliminar'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('compras/Form', [
            'proveedores' => User::conRolVigente('Proveedor')->orderBy('name')->get(['id', 'name']),
            'productos' => Producto::where('est', true)->orderBy('nombre')->get(['id', 'nombre', 'precio', 'stock']),
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
                Rule::exists('producto', 'id')->where('est', true),
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
