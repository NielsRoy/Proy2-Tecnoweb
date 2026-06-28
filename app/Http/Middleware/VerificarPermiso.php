<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guardia de Control de Acceso real (no solo ocultar el menu).
 *
 * Se usa en las rutas asi:  ->middleware('permiso:productos,registrar')
 * y aborta con 403 si el usuario logueado no tiene esa accion segun sus roles vigentes.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $modulo, string $accion): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tienePermiso($modulo, $accion)) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }

        return $next($request);
    }
}
