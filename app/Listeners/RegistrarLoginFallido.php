<?php

namespace App\Listeners;

use App\Models\Bitacora;
use Illuminate\Auth\Events\Failed;

/**
 * Registra un intento de inicio de sesion fallido (contrasena incorrecta o usuario
 * inexistente). El usuario suele ser null; se guarda el correo intentado en la descripcion.
 */
class RegistrarLoginFallido
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? '—';

        Bitacora::registrar(
            'login_fallido',
            "Intento de inicio de sesión fallido para «{$email}»",
            'auth',
            $event->user?->getAuthIdentifier(),
        );
    }
}
