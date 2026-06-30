<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un modulo = un item del menu / recurso del sistema. Puede tener submodulos (padre_id).
 *
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $icono
 * @property string|null $ruta
 * @property int $orden
 * @property int|null $padre_id
 * @property bool $activo
 */
#[Fillable(['clave', 'nombre', 'descripcion', 'icono', 'ruta', 'orden', 'padre_id', 'activo'])]
class Modulo extends Model
{
    protected $table = 'modulo';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** Acciones (sub-opciones) de este modulo. */
    public function acciones(): HasMany
    {
        return $this->hasMany(Accion::class, 'modulo_id');
    }

    /** Modulo padre (si este es un submodulo). */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'padre_id');
    }

    /** Submodulos directos, ordenados. */
    public function hijos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'padre_id')->orderBy('orden');
    }
}
