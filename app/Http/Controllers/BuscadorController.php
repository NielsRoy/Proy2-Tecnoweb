<?php

namespace App\Http\Controllers;

use App\Support\AccionesBuscables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Buscador global (requisito #9). PASO 1: sugiere ACCIONES del sistema que coinciden con el termino y a
 * las que el usuario TIENE permiso (AccionesBuscables ya parte de accionesPermitidas). No consulta hasta
 * que el front pulsa "Buscar"; devuelve JSON con hasta 5 sugerencias. En pasos futuros se sumaran
 * resultados de tablas (productos/usuarios/...), sin cambiar el contrato.
 */
class BuscadorController extends Controller
{
    private const MAX_SUGERENCIAS = 5;

    public function buscar(Request $request): JsonResponse
    {
        $termino = trim($request->query('q', ''));

        // Termino muy corto: no sugerimos nada (evita ruido con 1 caracter).
        if (Str::length($termino) < 2) {
            return response()->json(['acciones' => []]);
        }

        $palabras = collect(explode(' ', AccionesBuscables::normalizar($termino)))
            ->filter()
            ->values();

        $acciones = collect(AccionesBuscables::para($request->user()))
            // Coincide si TODAS las palabras del termino estan en el texto de la accion (subcadena).
            ->filter(fn (array $a) => $palabras->every(fn (string $w) => str_contains($a['texto'], $w)))
            ->take(self::MAX_SUGERENCIAS)
            ->map(fn (array $a) => [
                'label' => $a['label'],
                'url' => $a['url'],
                'icono' => $a['icono'],
                'modulo' => $a['modulo'],
            ])
            ->values();

        return response()->json(['acciones' => $acciones]);
    }
}
