<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Inventario;
use App\Models\Producto;
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
        $filtros = [
            'producto_id' => $request->integer('producto_id') ?: null,
            'tipo_movimiento' => $request->string('tipo_movimiento')->toString() ?: null,
            'motivo' => $request->string('motivo')->toString() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $movimientos = Inventario::query()
            ->with('producto:id,nombre')
            ->when($filtros['producto_id'], fn ($q, $id) => $q->where('producto_id', $id))
            ->when($filtros['tipo_movimiento'], fn ($q, $t) => $q->where('tipo_movimiento', $t))
            ->when($filtros['motivo'], fn ($q, $m) => $q->where('motivo', $m))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_movimiento', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_movimiento', '<=', $h))
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
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
