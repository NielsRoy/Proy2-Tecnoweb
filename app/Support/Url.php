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
     */
    public static function path(string $name): string
    {
        $base = rtrim(parse_url((string) config('app.url'), PHP_URL_PATH) ?? '', '/');

        return $base.route($name, absolute: false);
    }

    /**
     * Igual que path() pero seguro: null si la ruta con nombre no existe (CU sin construir).
     */
    public static function pathSiExiste(?string $name): ?string
    {
        return $name && Route::has($name) ? self::path($name) : null;
    }
}
