<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detalle de compra — intermedia con PK COMPUESTA (compra_id, producto_id). Ver nota de
 * DetalleVenta sobre la PK compuesta en Eloquent (se opera via relaciones, no por find()).
 *
 * @property int $compra_id
 * @property int $producto_id
 * @property int $cantidad
 * @property string $precio_unitario
 * @property string $subtotal
 */
#[Fillable(['compra_id', 'producto_id', 'cantidad', 'precio_unitario', 'subtotal'])]
class DetalleCompra extends Model
{
    protected $table = 'detalle_compra';

    // Append-only: la tabla no tiene updated_at (solo created_at).
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
