<?php

namespace App\Listeners;

use App\Models\Bitacora;
use Illuminate\Auth\Events\Logout;

/**
 * Registra el cierre de sesion. (El evento trae el usuario que cierra sesion.)
 */
class RegistrarLogout
{
    public function handle(Logout $event): void
    {
        Bitacora::registrar('logout', 'Cerró sesión', 'auth', $event->user?->getAuthIdentifier());
    }
}
