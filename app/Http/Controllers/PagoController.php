<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU7 Pagos (modulo "pagos") — VISTA ADMIN. Cobro de las cuotas PENDIENTES de las ventas a credito.
 * Solo se puede cobrar la PROXIMA cuota de cada venta (la de menor numero_cuota pendiente); el monto
 * es fijo (sin pago parcial). Las ventas al contado ya se pagan al registrarse, asi que aqui solo
 * aparecen las cuotas a credito. Reusa Pago::saldar (marca pagada y, si era la ultima, deja la venta
 * 'pagada'). El pago por QR (#10) queda pendiente: metodos disponibles efectivo/transferencia/tarjeta.
 */
class PagoController extends Controller
{
    private const METODOS = [Pago::METODO_EFECTIVO, Pago::METODO_TRANSFERENCIA, Pago::METODO_TARJETA];

    public function index(Request $request): Response
    {
        $clienteId = $request->integer('cliente_id') ?: null;

        $pendientes = Pago::with('venta.cliente:id,name')
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', Venta::ESTADO_ANULADA))
            ->when($clienteId, fn ($q, $id) => $q->whereHas('venta', fn ($qq) => $qq->where('cliente_id', $id)))
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero_cuota')
            ->get();

        // La proxima cuota de cada venta = la de menor numero_cuota pendiente (la unica cobrable).
        $minPorVenta = $pendientes->groupBy('venta_id')->map(fn ($g) => $g->min('numero_cuota'));

        $cuotas = $pendientes->map(fn (Pago $p) => [
            'id' => $p->id,
            'venta_id' => $p->venta_id,
            'cliente' => $p->venta?->cliente?->name,
            'numero_cuota' => $p->numero_cuota,
            'total_cuotas' => $p->venta?->numero_cuotas,
            'monto' => $p->monto,
            'fecha_vencimiento' => $p->fecha_vencimiento?->toDateString(),
            'es_proxima' => $p->numero_cuota === $minPorVenta->get($p->venta_id),
            // URL del flujo de pago por QR (respeta el subdirectorio); al confirmar vuelve a este index.
            'qr_url' => route('pagos.qr', ['pago' => $p->id, 'retorno' => 'pagos.index'], absolute: false),
        ])->values();

        return Inertia::render('pagos/Index', [
            'cuotas' => $cuotas,
            'clientes' => User::conRolVigente('Cliente')->orderBy('name')->get(['id', 'name']),
            'filtros' => ['cliente_id' => $clienteId],
            'metodos' => self::METODOS,
            'puedeRegistrar' => $request->user()->tienePermiso('pagos', 'registrar'),
        ]);
    }

    /**
     * Cobra una cuota: debe estar pendiente, su venta no anulada, y ser la PROXIMA de su venta.
     * El monto es el de la cuota (no se acepta del request: sin pago parcial).
     */
    public function pagar(Request $request, Pago $pago): RedirectResponse
    {
        $datos = $request->validate([
            'metodo' => ['required', Rule::in(self::METODOS)],
        ], [], ['metodo' => 'método de pago']);

        $pago->load('venta');

        abort_if($pago->estado === Pago::ESTADO_PAGADO, 422, 'La cuota ya está pagada.');
        abort_if($pago->venta?->estado === Venta::ESTADO_ANULADA, 422, 'La venta está anulada.');

        // Debe ser la proxima cuota pendiente de su venta (no saltarse el orden).
        $minPendiente = Pago::where('venta_id', $pago->venta_id)
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->min('numero_cuota');

        abort_if($pago->numero_cuota !== $minPendiente, 422, 'Debes pagar primero la cuota anterior.');

        DB::transaction(function () use ($pago, $datos) {
            Pago::saldar($pago, $datos['metodo']);

            Bitacora::registrar(
                'modificar',
                "Cobró la cuota #{$pago->numero_cuota} de la venta #{$pago->venta_id} (Bs {$pago->monto})",
                'pagos',
            );
        });

        $this->toastExito('Pago registrado.');

        return redirect()->route('pagos.index');
    }
}
