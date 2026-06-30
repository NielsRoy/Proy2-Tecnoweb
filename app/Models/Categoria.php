<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria de productos. `foto` = banner de la tienda; `orden` = secuencia de aparicion en la
 * tienda (no hay subcategorias). `est` = soft-delete (los productos la referencian).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $foto
 * @property int $orden
 * @property bool $est
 */
#[Fillable(['nombre', 'descripcion', 'foto', 'orden', 'est'])]
class Categoria extends Model
{
    protected $table = 'categoria';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'est' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
