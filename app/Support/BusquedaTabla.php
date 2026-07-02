<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filtro de texto reutilizable para las tablas de los índices (buscador global, paso 2). Aplica una
 * busqueda case-insensitive (Postgres `ilike`) sobre columnas directas y/o columnas de relaciones
 * (dot-notation en whereHas), agrupando todo en un OR. Lo usan los `consultaFiltrada()` cuando llega
 * el término `q`.
 */
class BusquedaTabla
{
    /**
     * @param  array<int, string>  $directas  columnas del propio modelo
     * @param  array<string, array<int, string>>  $relaciones  relacion => [columnas] (dot-notation)
     */
    public static function aplicar(Builder $query, string $termino, array $directas, array $relaciones = []): Builder
    {
        $like = '%'.trim($termino).'%';

        return $query->where(function (Builder $w) use ($like, $directas, $relaciones) {
            foreach ($directas as $col) {
                $w->orWhere($col, 'ilike', $like);
            }
            foreach ($relaciones as $rel => $cols) {
                $w->orWhereHas($rel, function (Builder $r) use ($like, $cols) {
                    $r->where(function (Builder $rr) use ($like, $cols) {
                        foreach ($cols as $c) {
                            $rr->orWhere($c, 'ilike', $like);
                        }
                    });
                });
            }
        });
    }
}
