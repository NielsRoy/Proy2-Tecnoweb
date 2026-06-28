<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Respuesta de login con destino dinamico: en vez del home fijo de Fortify (/dashboard),
 * manda al usuario a su URL de inicio segun sus permisos (User::urlInicio()), respetando
 * cualquier deep-link valido previo via redirect()->intended().
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($request->user()->urlInicio());
    }
}
