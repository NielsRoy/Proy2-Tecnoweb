<?php

namespace App\Listeners;

use App\Models\Bitacora;
use Illuminate\Auth\Events\Lockout;

/**
 * Registra el bloqueo por exceso de intentos de inicio de sesion (rate limiting de Fortify:
 * 5 intentos por minuto por correo+IP). No hay usuario autenticado en este punto.
 */
class RegistrarBloqueoLogin
{
    public function handle(Lockout $event): void
    {
        Bitacora::registrar(
            'login_bloqueado',
            'Bloqueo por demasiados intentos de inicio de sesión',
            'auth',
            null,
        );
    }
}
