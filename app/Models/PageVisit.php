<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador de visitas (vistas de pagina) por ruta. Una fila por pagina.
 *
 * @property int $id
 * @property string $route
 * @property int $visits
 */
#[Fillable(['route', 'visits'])]
class PageVisit extends Model
{
    /**
     * Registra una visita a la pagina indicada y devuelve el total ya incrementado.
     * El incremento (set visits = visits + 1) es atomico a nivel SQL.
     */
    public static function record(string $route): int
    {
        $visit = static::firstOrCreate(['route' => $route]);
        $visit->increment('visits');

        return $visit->visits;
    }
}
