<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * Respuesta de registro con destino dinamico: el nuevo usuario (rol Cliente por defecto)
 * aterriza en su URL de inicio segun sus permisos (User::urlInicio()) en vez del home fijo.
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended($request->user()->urlInicio());
    }
}
