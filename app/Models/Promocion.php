<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Promocion (CU6): descuento por producto. `est` = soft-delete (las ventas la referencian).
 *
 * @property int $id
 * @property int $producto_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $tipo_descuento
 * @property string $valor
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon $fecha_fin
 * @property bool $est
 */
#[Fillable(['producto_id', 'nombre', 'descripcion', 'tipo_descuento', 'valor', 'fecha_inicio', 'fecha_fin', 'est'])]
class Promocion extends Model
{
    protected $table = 'promocion';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'est' => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
