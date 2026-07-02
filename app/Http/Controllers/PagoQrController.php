<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Carrito;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use App\Services\PagoFacil\PagoFacilClient;
use App\Services\RegistrarVenta;
use App\Support\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pago por QR de PagoFacil (requisito #10) — flujo ASINCRONO con QR + polling desde el navegador.
 * Hay DOS variantes que comparten la misma pagina (pages/pagos/Qr.vue):
 *
 *  A) COBRO DE UNA CUOTA EXISTENTE (mostrar/generar/estado): paga la proxima cuota pendiente de una
 *     venta YA registrada (cuotas a credito, y ventas al contado que se cobran por otros medios). Al
 *     confirmarse, salda la cuota (Pago::saldar).
 *
 *  B) REGISTRO DIFERIDO DE UNA VENTA AL CONTADO (mostrarVenta/generarVenta/estadoVenta): la venta al
 *     contado por QR NO se registra hasta confirmar el pago. La "intencion de compra" (cliente, lineas,
 *     direccion) se guarda en SESION; recien al confirmarse el QR se llama a RegistrarVenta (crea la
 *     venta, descuenta stock y la deja pagada) y, si vino de la tienda, se vacia el carrito.
 *
 * Autorizacion (variante A): la cuota debe ser la PROXIMA pendiente de su venta (no anulada) y el
 * usuario debe ser el dueño de la venta O tener permiso admin de "pagos".
 */
class PagoQrController extends Controller
{
    // Clave de sesion donde vive la intencion de una venta contado+QR pendiente de confirmar (variante B).
    private const SESION_VENTA = 'qr_venta';

    // Rutas a las que puede volver el boton "Regresar" tras el pago (lista blanca).
    private const RETORNOS = ['inicio', 'ventas.index', 'pagos.index', 'mis-pagos.index', 'mis-compras.index'];

    // ─────────────────────────────────────────────────────────────────────────
    // Variante A: cobro de una cuota existente
    // ─────────────────────────────────────────────────────────────────────────

    /** Pagina del QR para una cuota existente: dispara la generacion y el polling desde el cliente. */
    public function mostrar(Request $request, Pago $pago): Response
    {
        $this->validarCuotaPagable($pago, $request);
        $pago->load('venta:id,numero_cuotas');

        return Inertia::render('pagos/Qr', [
            'titulo' => "Cuota {$pago->numero_cuota}/{$pago->venta?->numero_cuotas} de la venta #{$pago->venta_id}",
            'monto' => $pago->monto,
            'retornoUrl' => $this->retornoUrl($request),
            // URLs resueltas en el server (Url::path conserva el subdirectorio) para el polling AJAX.
            'generarUrl' => Url::path('pagos.qr.generar', $pago),
            'estadoUrl' => Url::path('pagos.qr.estado', $pago),
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

        $detalle = "Venta #{$pago->venta_id} - cuota {$pago->numero_cuota}/{$pago->venta->numero_cuotas}";

        try {
            $qr = $client->generarQr($paymentNumber, $client->datosCliente($pago->venta->cliente), $detalle);
        } catch (\Throwable $e) {
            return $this->errorQr($e);
        }

        $pago->update(['pagofacil_transaction_id' => $qr['transactionId']]);

        return response()->json(['qr_base64' => $qr['qrBase64'], 'expiration' => $qr['expirationDate']]);
    }

    /**
     * Consulta el estado del pago en PagoFacil. Si ya se pago, salda la cuota con su MONTO REAL
     * (el cobro en PagoFacil fue simbolico). Idempotente: si la cuota ya esta pagada, responde 'pagado'.
     */
    public function estado(Request $request, Pago $pago, PagoFacilClient $client): JsonResponse
    {
        $yaPagada = $pago->estado === Pago::ESTADO_PAGADO;

        if (! $yaPagada) {
            $this->validarCuotaPagable($pago, $request);
        } else {
            $this->autorizarCuota($pago, $request); // guardia de propiedad para el caso idempotente
        }

        if (empty($pago->payment_number)) {
            return response()->json(['estado' => $yaPagada ? 'pagado' : 'pendiente']);
        }

        $tx = $client->consultarTransaccion($pago->payment_number);

        if (! $yaPagada) {
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
        }

        return response()->json(['estado' => 'pagado', 'pago' => $this->detallesPago($tx, $pago->fresh())]);
    }

    /**
     * Pantalla de comprobante ("Pago confirmado") tras un pago por un metodo NO-QR (contado no-QR o
     * cobro de cuota en efectivo/transferencia/tarjeta). Los datos llegan por flash de sesion
     * (Controller::comprobantePago); si se entra sin ellos (recarga/URL directa), vuelve al inicio.
     */
    public function comprobante(Request $request): Response|RedirectResponse
    {
        $data = $request->session()->get('comprobante');

        if (! $data) {
            return redirect()->route('inicio');
        }

        // Variante "pedido": checkout del cliente al contado + efectivo (aun sin cobrar).
        if (($data['tipo'] ?? 'pago') === 'pedido') {
            return Inertia::render('pagos/Comprobante', [
                'tipo' => 'pedido',
                'concepto' => $data['concepto'],
                'pedido' => [
                    'monto' => $data['monto'],
                    'direccion' => $data['direccion'] ?? null,
                ],
                'retornoUrl' => Url::path($data['retorno']),
            ]);
        }

        return Inertia::render('pagos/Comprobante', [
            'tipo' => 'pago',
            'concepto' => $data['concepto'],
            'pago' => [
                'metodo' => $data['metodo'],
                'monto' => $data['monto'],
                'banco' => $data['banco'] ?? null,
                'cuenta' => $data['cuenta'] ?? null,
                'titular' => $data['titular'] ?? null,
                'fecha' => $data['fecha'] ?? null,
                'hora' => $data['hora'] ?? null,
            ],
            'retornoUrl' => Url::path($data['retorno']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Variante B: registro diferido de una venta al contado (no se registra hasta pagar)
    // ─────────────────────────────────────────────────────────────────────────

    /** Pagina del QR para una venta contado pendiente (la intencion vive en sesion). */
    public function mostrarVenta(Request $request): Response|RedirectResponse
    {
        $intento = $request->session()->get(self::SESION_VENTA);

        if (! $intento) {
            return redirect()->route('inicio');
        }

        return Inertia::render('pagos/Qr', [
            'titulo' => $intento['titulo'],
            'monto' => number_format((float) $intento['monto'], 2, '.', ''),
            'retornoUrl' => Url::path($intento['retorno']),
            'generarUrl' => Url::path('pagos.qr-venta.generar'),
            'estadoUrl' => Url::path('pagos.qr-venta.estado'),
            'pollSeconds' => app(PagoFacilClient::class)->pollSeconds(),
            'timeoutSeconds' => app(PagoFacilClient::class)->timeoutSeconds(),
        ]);
    }

    /** Genera (o regenera) el QR de la venta pendiente en sesion. */
    public function generarVenta(Request $request, PagoFacilClient $client): JsonResponse
    {
        $intento = $request->session()->get(self::SESION_VENTA);

        if (! $intento) {
            return response()->json(['message' => 'No hay una compra pendiente de pago.'], 422);
        }

        // Nuevo payment_number en cada (re)generacion (evita duplicados si el QR expiro).
        $paymentNumber = (string) Str::uuid();
        $comprador = User::whereKey($intento['payload']['cliente_id'])->firstOrFail();

        try {
            $qr = $client->generarQr($paymentNumber, $client->datosCliente($comprador), $intento['titulo']);
        } catch (\Throwable $e) {
            return $this->errorQr($e);
        }

        $intento['payment_number'] = $paymentNumber;
        $intento['transaction_id'] = $qr['transactionId'];
        $request->session()->put(self::SESION_VENTA, $intento);

        return response()->json(['qr_base64' => $qr['qrBase64'], 'expiration' => $qr['expirationDate']]);
    }

    /**
     * Consulta el estado del pago de la venta pendiente. Al confirmarse, RECIEN AHORA se registra la
     * venta (RegistrarVenta: crea la venta al contado ya pagada y descuenta stock), se vacia el carrito
     * si vino de la tienda y se limpia la sesion.
     */
    public function estadoVenta(Request $request, PagoFacilClient $client, RegistrarVenta $registrar): JsonResponse
    {
        $intento = $request->session()->get(self::SESION_VENTA);

        if (! $intento || empty($intento['payment_number'])) {
            return response()->json(['estado' => 'pendiente']);
        }

        $tx = $client->consultarTransaccion($intento['payment_number']);

        if (! $tx['paid']) {
            return response()->json(['estado' => 'pendiente']);
        }

        // Pago confirmado: registrar la venta (valida stock de nuevo por si cambio).
        try {
            $venta = $registrar->ejecutar($intento['payload']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'El pago se recibió pero no se pudo registrar la venta.',
                'detalle' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        // Guardar la referencia de PagoFacil en la cuota recien creada.
        $cuota = Pago::where('venta_id', $venta->id)->where('numero_cuota', 1)->first();
        $cuota?->update([
            'payment_number' => $intento['payment_number'],
            'pagofacil_transaction_id' => $intento['transaction_id'] ?? null,
        ]);

        if (($intento['origen'] ?? null) === 'tienda') {
            Carrito::where('cliente_id', $intento['payload']['cliente_id'])->delete();
        }

        $request->session()->forget(self::SESION_VENTA);

        return response()->json([
            'estado' => 'pagado',
            'pago' => $this->detallesPago($tx, $cuota),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Datos del pago para mostrar en la pantalla de exito (banco/cuenta/titular + fecha y hora).
     *
     * @param  array<string, mixed>  $tx
     * @return array<string, mixed>
     */
    private function detallesPago(array $tx, ?Pago $cuota): array
    {
        return [
            'metodo' => 'QR (PagoFacil)',
            'monto' => $cuota?->monto,
            'banco' => $tx['payerBank'] ?? null,
            'cuenta' => $tx['payerAccount'] ?? null,
            'titular' => $tx['payerName'] ?? null,
            'fecha' => $cuota?->fecha_pago?->format('d/m/Y'),
            'hora' => $cuota?->fecha_pago?->format('H:i:s'),
        ];
    }

    /** Respuesta JSON de error al generar el QR (deja la traza en el log y el motivo en el body). */
    private function errorQr(\Throwable $e): JsonResponse
    {
        report($e); // traza completa en storage/logs/laravel.log

        return response()->json([
            'message' => 'No se pudo generar el QR de PagoFacil.',
            'detalle' => $e->getMessage(),
        ], 502);
    }

    /**
     * Valida que la cuota se puede pagar y que el usuario tiene derecho: cuota pendiente, venta no
     * anulada, es la proxima cuota de su venta, y (dueño de la venta O permiso admin de pagos).
     */
    private function validarCuotaPagable(Pago $pago, Request $request): void
    {
        $this->autorizarCuota($pago, $request);

        abort_if($pago->estado === Pago::ESTADO_PAGADO, 422, 'La cuota ya está pagada.');
        abort_if($pago->venta->estado === Venta::ESTADO_ANULADA, 422, 'La venta está anulada.');

        $minPendiente = Pago::where('venta_id', $pago->venta_id)
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->min('numero_cuota');

        abort_if($pago->numero_cuota !== $minPendiente, 422, 'Debes pagar primero la cuota anterior.');
    }

    /** Guardia de propiedad de una cuota: dueño de la venta o permiso admin de pagos. */
    private function autorizarCuota(Pago $pago, Request $request): void
    {
        $pago->load('venta:id,cliente_id,estado');
        $user = $request->user();

        abort_if($pago->venta === null, 404);
        abort_if(
            ! $user->tienePermiso('pagos', 'registrar') && $pago->venta->cliente_id !== $user->id,
            403,
        );
    }

    /** Resuelve la URL de retorno desde el query param (lista blanca), con fallback razonable. */
    private function retornoUrl(Request $request): string
    {
        $retorno = $request->string('retorno')->toString();

        if (in_array($retorno, self::RETORNOS, true)) {
            return Url::path($retorno);
        }

        // Fallback: admin -> pagos; cliente -> inicio.
        return $request->user()->tienePermiso('pagos', 'registrar')
            ? Url::path('pagos.index')
            : Url::path('inicio');
    }
}
