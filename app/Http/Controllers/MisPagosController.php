<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Pago;
use App\Models\Venta;
use App\Support\Url;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perspectiva CLIENTE (modulo "mis_pagos"): el usuario logueado ve SU plan de pagos a credito activo
 * (cuotas pendientes, con la proxima cobrable) y el historial de cuotas ya pagadas, y puede pagar su
 * propia proxima cuota. Reusa la logica de PagoController (admin) acotada a sus ventas + guardia de
 * propiedad. Reusa Pago::saldar (marca pagada; al saldar la ultima, deja la venta 'pagada').
 */
class MisPagosController extends Controller
{
    private const METODOS = [Pago::METODO_EFECTIVO, Pago::METODO_TRANSFERENCIA, Pago::METODO_TARJETA];

    public function index(Request $request): Response
    {
        $uid = $request->user()->id;
        $filtros = [
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        // Plan activo: TODAS las cuotas pendientes del cliente (no se filtra por fecha; el filtro
        // de abajo es solo para el historial).
        $pendientes = Pago::with('venta:id,cliente_id,numero_cuotas')
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->whereHas('venta', fn ($q) => $q->where('cliente_id', $uid)->where('estado', '!=', Venta::ESTADO_ANULADA))
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero_cuota')
            ->get();

        // La proxima cuota de cada venta = la de menor numero_cuota pendiente (la unica cobrable).
        $minPorVenta = $pendientes->groupBy('venta_id')->map(fn ($g) => $g->min('numero_cuota'));

        $plan = $pendientes->map(fn (Pago $p) => [
            'id' => $p->id,
            'venta_id' => $p->venta_id,
            'numero_cuota' => $p->numero_cuota,
            'total_cuotas' => $p->venta?->numero_cuotas,
            'monto' => $p->monto,
            'fecha_vencimiento' => $p->fecha_vencimiento?->toDateString(),
            'es_proxima' => $p->numero_cuota === $minPorVenta->get($p->venta_id),
            // URL del flujo de pago por QR (Url::path conserva el subdirectorio en produccion);
            // al confirmar el pago vuelve a este index.
            'qr_url' => Url::path('pagos.qr', ['pago' => $p->id, 'retorno' => 'mis-pagos.index']),
        ])->values();

        // Historial: cuotas ya pagadas del cliente, filtradas por rango de FECHA DE PAGO.
        $historial = Pago::where('estado', Pago::ESTADO_PAGADO)
            ->whereHas('venta', fn ($q) => $q->where('cliente_id', $uid))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_pago', '<=', $h))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Pago $p) => [
                'venta_id' => $p->venta_id,
                'numero_cuota' => $p->numero_cuota,
                'monto' => $p->monto,
                'fecha_pago' => $p->fecha_pago?->format('d/m/Y H:i'),
                'metodo' => $p->metodo,
            ]);

        return Inertia::render('mis-pagos/Index', [
            'plan' => $plan,
            'historial' => $historial,
            'filtros' => $filtros,
            'metodos' => self::METODOS,
        ]);
    }

    /**
     * Paga la proxima cuota pendiente del PROPIO cliente. Mismas reglas que PagoController::pagar
     * (metodo valido, cuota no pagada, venta no anulada, solo la proxima) + guardia de propiedad.
     */
    public function pagar(Request $request, Pago $pago): RedirectResponse
    {
        $datos = $request->validate([
            'metodo' => ['required', Rule::in(self::METODOS)],
        ], [], ['metodo' => 'método de pago']);

        $pago->load('venta');

        // Guardia de propiedad: la cuota debe ser de una venta del usuario logueado.
        abort_if($pago->venta?->cliente_id !== $request->user()->id, 403);

        abort_if($pago->estado === Pago::ESTADO_PAGADO, 422, 'La cuota ya está pagada.');
        abort_if($pago->venta?->estado === Venta::ESTADO_ANULADA, 422, 'La venta está anulada.');

        $minPendiente = Pago::where('venta_id', $pago->venta_id)
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->min('numero_cuota');

        abort_if($pago->numero_cuota !== $minPendiente, 422, 'Debes pagar primero la cuota anterior.');

        DB::transaction(function () use ($pago, $datos) {
            Pago::saldar($pago, $datos['metodo']);

            Bitacora::registrar(
                'modificar',
                "Pagó la cuota #{$pago->numero_cuota} de su venta #{$pago->venta_id} (Bs {$pago->monto})",
                'mis_pagos',
            );
        });

        $this->toastExito('¡Pago realizado con éxito!');

        return redirect()->route('mis-pagos.index');
    }
}
