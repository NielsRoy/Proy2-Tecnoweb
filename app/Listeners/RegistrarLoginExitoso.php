<?php

namespace App\Listeners;

use App\Models\Bitacora;
use Illuminate\Auth\Events\Login;

/**
 * Registra en la bitacora un inicio de sesion exitoso. Auto-descubierto por Laravel
 * (clase en app/Listeners con handle() tipado con el evento).
 */
class RegistrarLoginExitoso
{
    public function handle(Login $event): void
    {
        Bitacora::registrar('login', 'Inició sesión', 'auth', $event->user?->getAuthIdentifier());
    }
}
