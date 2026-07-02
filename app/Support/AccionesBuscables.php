<?php

namespace App\Support;

use App\Models\Accion;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Construye la lista de ACCIONES BUSCABLES del usuario para el buscador global (requisito #9, paso 1):
 * una entrada por cada accion PERMITIDA al usuario (crear/ver/editar/... de cada modulo + gráficos del
 * Dashboard), con su etiqueta, palabras clave (sinonimos en español), URL destino (respeta subdirectorio)
 * e icono. El filtrado por el termino de busqueda lo hace BuscadorController.
 */
class AccionesBuscables
{
    /**
     * @return array<int, array{label:string, texto:string, url:string, icono:?string, modulo:string}>
     */
    public static function para(User $user): array
    {
        $entradas = [];

        foreach ($user->accionesPermitidas() as $accion) {
            $entrada = self::construir($accion);
            if ($entrada !== null) {
                $entradas[] = $entrada;
            }
        }

        return $entradas;
    }

    /**
     * @return array{label:string, texto:string, url:string, icono:?string, modulo:string}|null
     */
    private static function construir(Accion $accion): ?array
    {
        $modulo = $accion->modulo;
        if ($modulo === null) {
            return null;
        }

        $nombreMod = $modulo->nombre;
        $rutaIndex = $modulo->ruta;                                   // p. ej. 'usuarios.index' | 'dashboard'
        $prefijo = Str::endsWith((string) $rutaIndex, '.index')       // 'usuarios' (para derivar create)
            ? Str::beforeLast((string) $rutaIndex, '.index')
            : null;

        // Gráficos del Dashboard: la etiqueta es el propio nombre de la accion ("Gráfico: ...").
        if (Str::startsWith($accion->clave, 'graf_')) {
            return self::entrada($accion->nombre, [$accion->nombre, 'grafico', 'estadistica', 'dashboard'], $rutaIndex, $modulo->icono, $nombreMod);
        }

        // El resto: etiqueta + sinonimos + ruta destino segun la clave de la accion.
        [$label, $keywords, $ruta] = match ($accion->clave) {
            'registrar' => [
                "Crear en {$nombreMod}",
                ['crear', 'nuevo', 'nueva', 'registrar', 'agregar', 'añadir', $nombreMod],
                $prefijo ? "{$prefijo}.create" : null,
            ],
            'modificar' => [
                "Editar {$nombreMod}",
                ['editar', 'modificar', 'actualizar', $nombreMod],
                $rutaIndex,
            ],
            'eliminar' => [
                "Eliminar en {$nombreMod}",
                ['eliminar', 'borrar', 'anular', $nombreMod],
                $rutaIndex,
            ],
            'reportar' => [
                "Reportes de {$nombreMod}",
                ['reporte', 'reportes', 'exportar', 'pdf', 'excel', $nombreMod],
                $rutaIndex,
            ],
            'pagar' => [
                'Pagar cuota',
                ['pagar', 'pago', 'cuota', $nombreMod],
                $rutaIndex,
            ],
            // 'listar' y 'ver' (y cualquier otra accion base): abren la vista del modulo.
            default => [
                "Ver {$nombreMod}",
                ['ver', 'abrir', 'listar', 'lista', $nombreMod],
                $rutaIndex,
            ],
        };

        return self::entrada($label, $keywords, $ruta, $modulo->icono, $nombreMod);
    }

    /**
     * Arma la entrada final: resuelve la URL (null si la ruta no existe -> se descarta) y normaliza el
     * texto de busqueda (minusculas + sin tildes).
     *
     * @param  array<int, string>  $keywords
     * @return array{label:string, texto:string, url:string, icono:?string, modulo:string}|null
     */
    private static function entrada(string $label, array $keywords, ?string $ruta, ?string $icono, string $modulo): ?array
    {
        $url = Url::pathSiExiste($ruta);
        if ($url === null) {
            return null;
        }

        return [
            'label' => $label,
            'texto' => self::normalizar($label.' '.implode(' ', $keywords)),
            'url' => $url,
            'icono' => $icono,
            'modulo' => $modulo,
        ];
    }

    /** Normaliza para comparar: minusculas + sin tildes/ñ (Str::ascii). */
    public static function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii($texto));
    }
}
