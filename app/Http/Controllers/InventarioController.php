<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Inventario;
use App\Models\Producto;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU5 Inventario (modulo "inventarios"): libro de movimientos de stock (append-only).
 *
 * Lo que se hace aqui MANUALMENTE es registrar un AJUSTE (ingreso/salida con motivo "ajuste").
 * El libro es append-only: no se edita ni se revierte un asiento; para corregir se registra OTRO
 * ajuste en sentido contrario. Los movimientos de compra/venta los generan esos CU, no esta
 * pantalla. Toda mutacion de stock pasa por Inventario::registrarMovimiento dentro de una transaccion.
 */
class InventarioController extends Controller
{
    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $movimientos = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Inventario $m) => [
                'id' => $m->id,
                'fecha' => $m->fecha_movimiento?->format('d/m/Y'),
                'producto' => $m->producto?->nombre,
                'tipo_movimiento' => $m->tipo_movimiento,
                'cantidad' => $m->cantidad,
                'motivo' => $m->motivo,
            ]);

        return Inertia::render('inventarios/Index', [
            'movimientos' => $movimientos,
            'filtros' => $filtros,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'puedeCrear' => $request->user()->tienePermiso('inventarios', 'registrar'),
        ]);
    }

    /** Reporte (PDF/Excel/CSV) del libro de inventario filtrado. Ruta con permiso:inventarios,listar. */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $movimientos = $query->get();

        $columnas = ['Fecha', 'Producto', 'Tipo', 'Cantidad', 'Motivo'];
        $filas = $movimientos->map(fn (Inventario $m) => [
            $m->fecha_movimiento?->format('d/m/Y'),
            $m->producto?->nombre,
            $m->tipo_movimiento === Inventario::INGRESO ? 'Ingreso' : 'Salida',
            $m->cantidad,
            ucfirst($m->motivo),
        ])->all();

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Inventario',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['Total: '.$movimientos->count().' movimientos', '', '', '', ''],
        ], 'inventario');
    }

    /**
     * Consulta del libro aplicando filtros (producto/tipo/motivo/fechas). Compartida por index y reporte.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'producto_id' => $request->integer('producto_id') ?: null,
            'tipo_movimiento' => $request->string('tipo_movimiento')->toString() ?: null,
            'motivo' => $request->string('motivo')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $query = Inventario::query()
            ->with('producto:id,nombre')
            ->when($filtros['producto_id'], fn ($q, $id) => $q->where('producto_id', $id))
            ->when($filtros['tipo_movimiento'], fn ($q, $t) => $q->where('tipo_movimiento', $t))
            ->when($filtros['motivo'], fn ($q, $m) => $q->where('motivo', $m))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_movimiento', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_movimiento', '<=', $h))
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['producto_id']) {
            $partes[] = 'Producto: '.(Producto::find($f['producto_id'])?->nombre ?? $f['producto_id']);
        }
        if ($f['tipo_movimiento']) {
            $partes[] = 'Tipo: '.ucfirst($f['tipo_movimiento']);
        }
        if ($f['motivo']) {
            $partes[] = 'Motivo: '.ucfirst($f['motivo']);
        }
        if ($f['desde']) {
            $partes[] = 'Desde: '.$f['desde'];
        }
        if ($f['hasta']) {
            $partes[] = 'Hasta: '.$f['hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Sin filtros (todos los movimientos)';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    public function create(): Response
    {
        return Inertia::render('inventarios/Form', [
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $producto = Producto::findOrFail($datos['producto_id']);

        // Salida no puede dejar el stock negativo (validacion amigable en espanol).
        if ($datos['tipo_movimiento'] === Inventario::SALIDA && $producto->stock < $datos['cantidad']) {
            return back()->withErrors([
                'cantidad' => "Stock insuficiente: «{$producto->nombre}» tiene {$producto->stock} unidades.",
            ])->withInput();
        }

        DB::transaction(function () use ($producto, $datos) {
            Inventario::registrarMovimiento(
                $producto,
                $datos['tipo_movimiento'],
                $datos['cantidad'],
                Inventario::MOTIVO_AJUSTE, // el motivo lo fija el server, no el request
                $datos['fecha_movimiento'],
            );

            $tipo = $datos['tipo_movimiento'] === Inventario::INGRESO ? 'ingreso' : 'salida';
            Bitacora::registrar(
                'crear',
                "Registró un ajuste de {$tipo} ({$datos['cantidad']}) de «{$producto->nombre}»",
                'inventarios',
            );
        });

        $this->toastExito('Movimiento de inventario registrado.');

        return redirect()->route('inventarios.index');
    }

    /**
     * Reglas de validacion (en espanol via lang/es). El motivo NO se valida: lo fija el server
     * en "ajuste".
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'producto_id' => ['required', 'integer', 'exists:producto,id'],
            'tipo_movimiento' => ['required', 'in:'.Inventario::INGRESO.','.Inventario::SALIDA],
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'fecha_movimiento' => ['required', 'date'],
        ], [], [
            'producto_id' => 'producto',
            'tipo_movimiento' => 'tipo de movimiento',
            'fecha_movimiento' => 'fecha',
        ]);
    }
}
