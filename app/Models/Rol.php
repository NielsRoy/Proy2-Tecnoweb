<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un rol agrupa permisos (acciones). Ej: Propietario, Vendedor, Cliente, Proveedor.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activo
 */
#[Fillable(['nombre', 'descripcion', 'activo'])]
class Rol extends Model
{
    protected $table = 'rol';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** Acciones que tiene este rol (la matriz de acceso, fila por rol). */
    public function acciones(): BelongsToMany
    {
        // La matriz es un pivote puro (sync inserta/borra filas, nunca las actualiza): sin timestamps.
        return $this->belongsToMany(Accion::class, 'rol_accion', 'rol_id', 'accion_id');
    }

    /** Usuarios que tienen este rol (con vigencia en el pivote). */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_rol', 'rol_id', 'user_id')
            ->withPivot(['fini', 'ffin', 'activo'])
            ->withTimestamps();
    }
}
