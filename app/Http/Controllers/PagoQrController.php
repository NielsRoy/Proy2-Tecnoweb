<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Pago;
use App\Models\Venta;
use App\Services\PagoFacil\PagoFacilClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pago por QR de PagoFacil (requisito #10) — flujo ASINCRONO comun a los 4 puntos de pago del sistema
 * (contado admin, contado tienda, cuota a credito admin, cuota a credito cliente). Todo gira en torno a
 * UNA cuota `pago`:
 *   - mostrar(): pagina Inertia con el QR + polling desde el navegador.
 *   - generar(): pide el QR a PagoFacil y guarda payment_number + pagofacil_transaction_id (JSON).
 *   - estado():  consulta el estado; si esta pagado, salda la cuota con Pago::saldar (JSON, idempotente).
 *
 * Autorizacion: la cuota debe ser la PROXIMA pendiente de su venta (no anulada) y el usuario debe ser
 * el dueño de la venta O tener permiso admin de "pagos" (cobrar cualquier venta). Reusa las mismas
 * reglas que PagoController::pagar / MisPagosController::pagar.
 */
class PagoQrController extends Controller
{
    // Rutas a las que se puede volver tras el pago (lista blanca; el front navega ahi al confirmarse).
    private const RETORNOS = ['inicio', 'ventas.index', 'pagos.index', 'mis-pagos.index', 'mis-compras.index'];

    /** Pagina del QR: dispara la generacion y el polling desde el cliente. */
    public function mostrar(Request $request, Pago $pago): Response
    {
        $this->validarCuotaPagable($pago, $request);
        $pago->load('venta:id,numero_cuotas,monto_total');

        return Inertia::render('pagos/Qr', [
            'cuota' => [
                'id' => $pago->id,
                'venta_id' => $pago->venta_id,
                'numero_cuota' => $pago->numero_cuota,
                'total_cuotas' => $pago->venta?->numero_cuotas,
                'monto' => $pago->monto,
            ],
            'retornoUrl' => $this->retornoUrl($request),
            // URLs resueltas en el server (respetan el subdirectorio) para el polling AJAX del cliente.
            'generarUrl' => route('pagos.qr.generar', $pago, absolute: false),
            'estadoUrl' => route('pagos.qr.estado', $pago, absolute: false),
            'pollSeconds' => app(PagoFacilClient::class)->pollSeconds(),
            'timeoutSeconds' => app(PagoFacilClient::class)->timeoutSeconds(),
        ]);
    }

    /** Genera (o regenera) el QR de la cuota. Devuelve el QR en base64 y su expiracion. */
    public function generar(Request $request, Pago $pago, PagoFacilClient $client): JsonResponse
    {
        $this->validarCuotaPagable($pago, $request);
        $pago->load(['venta:id,cliente_id,numero_cuotas', 'venta.cliente:id,name,email,ci,telefono']);

        // payment_number = companyTransactionId (UUID) con el que luego se consulta el estado.
        $paymentNumber = (string) Str::uuid();
        $pago->update(['payment_number' => $paymentNumber]);

        $comprador = $pago->venta->cliente;
        $detalle = "Venta #{$pago->venta_id} - cuota {$pago->numero_cuota}/{$pago->venta->numero_cuotas}";

        try {
            $qr = $client->generarQr($pago, $client->datosCliente($comprador), $detalle);
        } catch (\Throwable $e) {
            report($e); // deja la traza completa en storage/logs/laravel.log

            // Devolvemos el mensaje en el JSON para verlo en el navegador (Network) y en la pagina.
            return response()->json([
                'message' => 'No se pudo generar el QR de PagoFacil.',
                'detalle' => $e->getMessage(),
            ], 502);
        }

        $pago->update(['pagofacil_transaction_id' => $qr['transactionId']]);

        return response()->json([
            'qr_base64' => $qr['qrBase64'],
            'expiration' => $qr['expirationDate'],
        ]);
    }

    /**
     * Consulta el estado del pago en PagoFacil. Si ya se pago, salda la cuota con su MONTO REAL
     * (el cobro en PagoFacil fue simbolico). Idempotente: si la cuota ya esta pagada, responde 'pagado'.
     */
    public function estado(Request $request, Pago $pago, PagoFacilClient $client): JsonResponse
    {
        if ($pago->estado === Pago::ESTADO_PAGADO) {
            return response()->json(['estado' => 'pagado']);
        }

        $this->validarCuotaPagable($pago, $request);

        if (empty($pago->payment_number)) {
            return response()->json(['estado' => 'pendiente']);
        }

        $tx = $client->consultarTransaccion($pago->payment_number);

        if (! $tx['paid']) {
            return response()->json(['estado' => 'pendiente']);
        }

        DB::transaction(function () use ($pago) {
            Pago::saldar($pago, Pago::METODO_QR);

            Bitacora::registrar(
                'modificar',
                "Pagó por QR la cuota #{$pago->numero_cuota} de la venta #{$pago->venta_id} (Bs {$pago->monto})",
                'pagos',
            );
        });

        return response()->json(['estado' => 'pagado']);
    }

    /**
     * Valida que la cuota se puede pagar y que el usuario tiene derecho: cuota pendiente, venta no
     * anulada, es la proxima cuota de su venta, y (dueño de la venta O permiso admin de pagos).
     */
    private function validarCuotaPagable(Pago $pago, Request $request): void
    {
        $pago->load('venta:id,cliente_id,estado');
        $user = $request->user();

        abort_if($pago->venta === null, 404);

        $esAdmin = $user->tienePermiso('pagos', 'registrar');
        $esDueno = $pago->venta->cliente_id === $user->id;
        abort_if(! $esAdmin && ! $esDueno, 403);

        abort_if($pago->estado === Pago::ESTADO_PAGADO, 422, 'La cuota ya está pagada.');
        abort_if($pago->venta->estado === Venta::ESTADO_ANULADA, 422, 'La venta está anulada.');

        $minPendiente = Pago::where('venta_id', $pago->venta_id)
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->min('numero_cuota');

        abort_if($pago->numero_cuota !== $minPendiente, 422, 'Debes pagar primero la cuota anterior.');
    }

    /** Resuelve la URL de retorno desde el query param (lista blanca), con fallback razonable. */
    private function retornoUrl(Request $request): string
    {
        $retorno = $request->string('retorno')->toString();

        if (in_array($retorno, self::RETORNOS, true)) {
            return route($retorno, absolute: false);
        }

        // Fallback: admin -> pagos; cliente -> inicio.
        return $request->user()->tienePermiso('pagos', 'registrar')
            ? route('pagos.index', absolute: false)
            : route('inicio', absolute: false);
    }
}
