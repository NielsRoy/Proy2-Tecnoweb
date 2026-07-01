<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class Url
{
    /**
     * Path de una ruta con nombre que CONSERVA el subdirectorio de APP_URL.
     *
     * `route($name, absolute: false)` devuelve la ruta SIN la raiz (p. ej. "/productos"),
     * perdiendo el subpath cuando la app vive en un subdirectorio en produccion. Aqui se
     * antepone el path de APP_URL (mismo mecanismo que hornea Wayfinder en los links del
     * front), de modo que en produccion sale ".../inf513/grupo25sa/proyecto3/productos" y
     * en local (APP_URL sin path) sale "/productos". Pensado para rutas SIN parametros
     * (modulos/acciones del menu dinamico).
     *
     * Acepta parametros de ruta (modelo/array) igual que route(); ej.
     * Url::path('pagos.qr', ['pago' => 5, 'retorno' => 'inicio']) -> ".../qr/5?retorno=inicio".
     *
     * ⚠️ SOLO para hrefs que consume el navegador / Inertia <Link> (client-side), donde un path
     * root-relative se resuelve contra el origen y por eso necesita el subpath. NO usar en un
     * `redirect()`/`url()->to()` server-side: esos YA anteponen la raiz de la peticion (con el
     * subdirectorio) y el subpath saldria DUPLICADO. Para redirects usa `route($name, absolute: false)`.
     */
    public static function path(string $name, mixed $parameters = []): string
    {
        $base = rtrim(parse_url((string) config('app.url'), PHP_URL_PATH) ?? '', '/');

        return $base.route($name, $parameters, absolute: false);
    }

    /**
     * Igual que path() pero seguro: null si la ruta con nombre no existe (CU sin construir).
     */
    public static function pathSiExiste(?string $name, mixed $parameters = []): ?string
    {
        return $name && Route::has($name) ? self::path($name, $parameters) : null;
    }
}
