<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

abstract class Controller
{
    /** Etiquetas legibles de cada metodo de pago (para la pantalla de comprobante). */
    private const ETIQUETA_METODO = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'qr' => 'QR (PagoFacil)',
    ];

    /**
     * Estandar de alerta del sistema: emite un "toast" para la siguiente respuesta.
     * Lo consume el cliente (lib/flashToast.ts -> vue-sonner). Todos los controladores
     * heredan estos helpers, asi toda operacion de modificacion usa la MISMA alerta.
     */

    /** Alerta de exito (toast verde). */
    protected function toastExito(string $mensaje): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);
    }

    /** Alerta de error (toast rojo). */
    protected function toastError(string $mensaje): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);
    }

    /**
     * Estandar de comprobante: tras cualquier pago EFECTIVO (contado no-QR o cobro de cuota) redirige a
     * la pantalla de "Pago confirmado" (la misma que muestra el QR). Los datos van por flash de sesion y
     * los lee PagoQrController::comprobante. `$retorno` = nombre de ruta del boton "Regresar".
     */
    protected function comprobantePago(Pago $cuota, string $concepto, string $retorno): RedirectResponse
    {
        session()->flash('comprobante', [
            'concepto' => $concepto,
            'metodo' => self::ETIQUETA_METODO[$cuota->metodo] ?? ucfirst((string) $cuota->metodo),
            'monto' => $cuota->monto,
            'fecha' => $cuota->fecha_pago?->format('d/m/Y'),
            'hora' => $cuota->fecha_pago?->format('H:i:s'),
            'retorno' => $retorno,
        ]);

        return redirect()->route('pagos.comprobante');
    }

    /**
     * Pantalla de "Pedido realizado": tras un checkout del cliente al contado + efectivo (pedido, que
     * AUN no se cobra) muestra la misma pantalla del comprobante pero con el texto de pedido (paga en
     * efectivo al recibir). Reusa el flujo del comprobante (flash de sesion -> pagos.comprobante).
     * `$retorno` = nombre de ruta del boton "Regresar".
     */
    protected function comprobantePedido(Venta $venta, string $retorno): RedirectResponse
    {
        session()->flash('comprobante', [
            'tipo' => 'pedido',
            'concepto' => "Pedido #{$venta->id}",
            'monto' => $venta->monto_total,
            'direccion' => $venta->direccion_envio,
            'retorno' => $retorno,
        ]);

        return redirect()->route('pagos.comprobante');
    }
}
