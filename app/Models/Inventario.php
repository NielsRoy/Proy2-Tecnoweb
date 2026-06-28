<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventario (CU5): libro de movimientos de stock (append-only).
 *
 * @property int $id
 * @property int $producto_id
 * @property int $cantidad
 * @property string $tipo_movimiento
 * @property \Illuminate\Support\Carbon $fecha_movimiento
 * @property string $motivo
 */
#[Fillable(['producto_id', 'cantidad', 'tipo_movimiento', 'fecha_movimiento', 'motivo'])]
class Inventario extends Model
{
    protected $table = 'inventario';

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'fecha_movimiento' => 'date',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
