<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una accion = sub-opcion de un modulo (registrar/modificar/eliminar/listar...).
 * `clave` es estable; el guardia comprueba permiso por (modulo.clave, accion.clave).
 *
 * @property int $id
 * @property int $modulo_id
 * @property string $nombre
 * @property string $clave
 * @property string|null $ruta
 * @property string|null $descripcion
 * @property bool $est
 */
#[Fillable(['modulo_id', 'nombre', 'clave', 'ruta', 'descripcion', 'est'])]
class Accion extends Model
{
    protected $table = 'accion';

    protected function casts(): array
    {
        return ['est' => 'boolean'];
    }

    /** Modulo al que pertenece esta accion. */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    /** Roles que tienen habilitada esta accion (la matriz de acceso, columna por accion). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_accion', 'accion_id', 'rol_id')
            ->withTimestamps();
    }
}
