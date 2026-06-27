<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador de visitas (vistas de pagina) por ruta. Una fila por pagina.
 *
 * @property int $id
 * @property string $ruta
 * @property int $visitas
 */
#[Fillable(['ruta', 'visitas'])]
class VisitaPagina extends Model
{
    protected $table = 'visitas_pagina';

    /**
     * Registra una visita a la pagina indicada y devuelve el total ya incrementado.
     * El incremento (set visitas = visitas + 1) es atomico a nivel SQL.
     */
    public static function registrar(string $ruta): int
    {
        $visita = static::firstOrCreate(['ruta' => $ruta]);
        $visita->increment('visitas');

        return $visita->visitas;
    }
}
