<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
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
}
