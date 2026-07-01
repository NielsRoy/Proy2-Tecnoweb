<?php

namespace App\Services\PagoFacil;

use App\Models\Pago;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP de la API de PagoFacil (metodo QR). Encapsula los endpoints que usamos:
 *   POST /login              -> autenticacion (headers tcTokenService / tcTokenSecret)
 *   POST /generate-qr        -> genera el cobro y devuelve el QR en base64
 *   POST /query-transaction  -> consulta el estado del pago (para el polling)
 *   POST /list-enabled-services -> resuelve el paymentMethodId si no viene en config
 *
 * Reimplementacion en Laravel del PagoFacilClient.java del sistema por correo: usa el facade Http
 * (Guzzle) con arrays JSON nativos (sin armar JSON a mano). Todas las respuestas de PagoFacil
 * envuelven los datos en la clave "values". El accessToken se cachea segun expiresInMinutes.
 *
 * El cobro real en PagoFacil es un monto SIMBOLICO (config 'amount', credenciales del curso); la
 * cuota del sistema se salda luego con su MONTO REAL. Ver proyecto/qr_pagofacil.md.
 */
class PagoFacilClient
{
    private const CACHE_TOKEN = 'pagofacil.access_token';

    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->config = config('services.pagofacil');
    }

    /**
     * Genera un QR de cobro para una cuota. Devuelve el transactionId de PagoFacil, el QR en base64
     * y la fecha de expiracion. `$cliente` son los datos fiscales ya resueltos (ver datosCliente()).
     *
     * @param  array{name:string, document:string, phone:string, email:string, code:string}  $cliente
     * @return array{transactionId:?string, qrBase64:?string, expirationDate:?string, status:int}
     */
    public function generarQr(Pago $cuota, array $cliente, string $detalle): array
    {
        $amount = $this->config['amount'];

        $body = [
            'paymentMethod' => $this->resolverPaymentMethodId(),
            'clientName' => $cliente['name'],
            'documentType' => (int) $this->config['document_type'],
            'documentId' => $cliente['document'],
            'phoneNumber' => $cliente['phone'],
            'email' => $cliente['email'],
            'paymentNumber' => (string) $cuota->payment_number,
            'amount' => $amount,
            'currency' => (int) $this->config['currency'],
            'clientCode' => $cliente['code'],
            'callbackUrl' => $this->config['callback_url'],
            'orderDetail' => [[
                'serial' => 1,
                'product' => $detalle,
                'quantity' => 1,
                'price' => $amount,
                'discount' => 0,
                'total' => $amount,
            ]],
        ];

        $values = $this->postAuth('/generate-qr', $body)->json('values') ?? [];

        $qrBase64 = $values['qrBase64'] ?? null;
        if (empty($qrBase64)) {
            throw new RuntimeException('PagoFacil no devolvió el QR.');
        }

        return [
            'transactionId' => isset($values['transactionId']) ? (string) $values['transactionId'] : null,
            'qrBase64' => $qrBase64,
            'expirationDate' => $values['expirationDate'] ?? null,
            'status' => (int) ($values['status'] ?? 0),
        ];
    }

    /**
     * Consulta el estado de una transaccion por su companyTransactionId (= payment_number).
     * `paid` es true cuando ya hay paymentDate (pago real registrado).
     *
     * @return array{paymentStatus:int, paid:bool, paymentDate:?string, paymentTime:?string, payerName:?string, payerBank:?string, payerAccount:?string}
     */
    public function consultarTransaccion(string $companyTransactionId): array
    {
        $values = $this->postAuth('/query-transaction', [
            'companyTransactionId' => $companyTransactionId,
        ])->json('values') ?? [];

        $paymentDate = $values['paymentDate'] ?? null;

        return [
            'paymentStatus' => (int) ($values['paymentStatus'] ?? 0),
            'paid' => ! empty($paymentDate),
            'paymentDate' => $paymentDate,
            'paymentTime' => $values['paymentTime'] ?? null,
            'payerName' => $values['payerName'] ?? null,
            'payerBank' => $values['payerBank'] ?? null,
            'payerAccount' => $values['payerAccount'] ?? null,
        ];
    }

    /**
     * Resuelve los datos fiscales del cliente para el QR. Si use_env_client=true usa los valores por
     * defecto del .env; si es false usa los datos reales del comprador (con fallback al .env si faltan).
     *
     * @return array{name:string, document:string, phone:string, email:string, code:string}
     */
    public function datosCliente(User $comprador): array
    {
        if ($this->config['use_env_client']) {
            return [
                'name' => $this->config['client_name'],
                'document' => $this->config['client_document'],
                'phone' => $this->config['client_phone'],
                'email' => $this->config['client_email'],
                'code' => $this->config['client_code'],
            ];
        }

        return [
            'name' => $comprador->name ?: $this->config['client_name'],
            'document' => $comprador->ci ?: $this->config['client_document'],
            'phone' => $comprador->telefono ?: $this->config['client_phone'],
            'email' => $comprador->email ?: $this->config['client_email'],
            'code' => 'U'.$comprador->id,
        ];
    }

    /** Segundos entre sondeos (para el frontend). */
    public function pollSeconds(): int
    {
        return (int) $this->config['poll_seconds'];
    }

    /** Segundos de vida del QR antes de considerarlo expirado (para el frontend). */
    public function timeoutSeconds(): int
    {
        return (int) $this->config['qr_timeout_seconds'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Interno: autenticacion y POST con Bearer
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Obtiene el accessToken (cacheado). POST /login con las credenciales en headers. El token se
     * guarda en cache con un TTL derivado de expiresInMinutes (margen de 1 min).
     */
    private function token(): string
    {
        $token = Cache::get(self::CACHE_TOKEN);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $service = $this->config['token_service'];
        $secret = $this->config['token_secret'];
        if (empty($service) || empty($secret)) {
            throw new RuntimeException('Faltan credenciales de PagoFacil (PAGOFACIL_TOKEN_SERVICE / PAGOFACIL_TOKEN_SECRET).');
        }

        // Body {} literal (los datos van en los headers). withBody garantiza un objeto JSON vacio.
        $resp = $this->baseRequest()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'tcTokenService' => $service,
                'tcTokenSecret' => $secret,
            ])
            ->withBody('{}', 'application/json')
            ->post($this->url('/login'));

        if ($resp->failed()) {
            throw new RuntimeException('Login de PagoFacil falló (HTTP '.$resp->status().').');
        }

        $values = $resp->json('values') ?? [];
        $token = $values['accessToken'] ?? null;
        if (empty($token)) {
            throw new RuntimeException('Login de PagoFacil sin accessToken.');
        }

        $minutes = (float) ($values['expiresInMinutes'] ?? 10);
        $ttl = max(60, (int) ($minutes * 60) - 60); // margen de 1 min
        Cache::put(self::CACHE_TOKEN, $token, $ttl);

        return $token;
    }

    /**
     * POST autenticado con Bearer. Reintenta una vez si el token fue rechazado (401/403): olvida el
     * token cacheado y vuelve a autenticarse. $body puede ser un array (se envia como JSON) o una
     * cadena JSON literal (p. ej. '{}' para cuerpos "objeto vacio").
     *
     * @param  array<string, mixed>|string  $body
     */
    private function postAuth(string $path, array|string $body): Response
    {
        $resp = $this->doPost($path, $body, $this->token());

        if ($resp->status() === 401 || $resp->status() === 403) {
            Cache::forget(self::CACHE_TOKEN);
            $resp = $this->doPost($path, $body, $this->token());
        }

        if ($resp->failed()) {
            throw new RuntimeException('PagoFacil '.$path.' falló (HTTP '.$resp->status().').');
        }

        return $resp;
    }

    /**
     * @param  array<string, mixed>|string  $body
     */
    private function doPost(string $path, array|string $body, string $token): Response
    {
        $request = $this->baseRequest()->withToken($token);

        if (is_string($body)) {
            return $request->withBody($body, 'application/json')->post($this->url($path));
        }

        return $request->asJson()->post($this->url($path), $body);
    }

    /**
     * Peticion base con timeout y verificacion SSL segun config. En produccion verify_ssl=true;
     * en local (Windows sin bundle de CA) se puede desactivar con PAGOFACIL_VERIFY_SSL=false.
     */
    private function baseRequest(): PendingRequest
    {
        $request = Http::timeout(30);

        if (! $this->config['verify_ssl']) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * paymentMethodId: de la config si esta seteado, o del primer servicio habilitado
     * (POST /list-enabled-services). Se calcula una sola vez por request.
     */
    private function resolverPaymentMethodId(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $configured = $this->config['payment_method'];
        if (! empty($configured)) {
            return $cached = (int) $configured;
        }

        $values = $this->postAuth('/list-enabled-services', '{}')->json('values') ?? [];
        // La respuesta es un arreglo de servicios; tomamos el paymentMethodId del primero.
        $id = $values[0]['paymentMethodId'] ?? ($values['paymentMethodId'] ?? null);
        if ($id === null) {
            throw new RuntimeException('No se pudo resolver el paymentMethodId de PagoFacil.');
        }

        return $cached = (int) $id;
    }

    private function url(string $path): string
    {
        $base = $this->config['base_url'];
        if (empty($base)) {
            throw new RuntimeException('Falta PAGOFACIL_BASE_URL en la configuración.');
        }

        return $base.$path;
    }
}
