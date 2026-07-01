<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perspectiva CLIENTE (modulo "mis_compras"): el usuario logueado ve SU historial de compras y el
 * detalle de cada una. Acotado a sus propias ventas (cliente_id = user id); el detalle refuerza con
 * un abort 403 si la venta no es suya. Reusa la forma del detalle de VentaController::show.
 */
class MisComprasController extends Controller
{
    public function index(Request $request): Response
    {
        $compras = $request->user()->ventas()
            ->withCount('detalles')
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Venta $v) => [
                'id' => $v->id,
                'fecha' => $v->fecha_venta?->format('d/m/Y'),
                'items' => $v->detalles_count,
                'monto_total' => $v->monto_total,
                'tipo_pago' => $v->tipo_pago,
                'estado_pago' => $v->estado_pago,
                'estado' => $v->estado,
            ]);

        return Inertia::render('mis-compras/Index', [
            'compras' => $compras,
        ]);
    }

    public function show(Request $request, Venta $venta): Response
    {
        // Guardia de propiedad: solo el dueño de la venta puede ver su detalle.
        abort_if($venta->cliente_id !== $request->user()->id, 403);

        $venta->load('detalles.producto:id,nombre,precio', 'detalles.promocion:id,nombre');

        return Inertia::render('mis-compras/Show', [
            'venta' => [
                'id' => $venta->id,
                'fecha' => $venta->fecha_venta?->format('d/m/Y'),
                'direccion_envio' => $venta->direccion_envio,
                'tipo_pago' => $venta->tipo_pago,
                'numero_cuotas' => $venta->numero_cuotas,
                'monto_total' => $venta->monto_total,
                'estado_pago' => $venta->estado_pago,
                'estado' => $venta->estado,
                'detalles' => $venta->detalles->map(fn ($d) => [
                    'producto' => $d->producto?->nombre,
                    'promocion' => $d->promocion?->nombre,
                    'cantidad' => $d->cantidad,
                    'precio_base' => $d->producto?->precio,    // precio de catalogo actual
                    'precio_unitario' => $d->precio_unitario,  // precio aplicado (con promo)
                    'subtotal' => $d->subtotal,
                ]),
                'cuotas' => $venta->pagos()->orderBy('numero_cuota')->get()->map(fn (Pago $p) => [
                    'numero_cuota' => $p->numero_cuota,
                    'monto' => $p->monto,
                    'fecha_vencimiento' => $p->fecha_vencimiento?->toDateString(),
                    'fecha_pago' => $p->fecha_pago?->toDateString(),
                    'metodo' => $p->metodo,
                    'estado' => $p->estado,
                ]),
            ],
        ]);
    }
}
