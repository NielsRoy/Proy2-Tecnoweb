<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detalle de venta — intermedia con PK COMPUESTA (venta_id, producto_id). Eloquent no soporta
 * PK compuesta nativamente: se desactiva la PK autoincremental y se opera via relaciones/consultas
 * (no por find()). promocion_id = promo aplicada a esta linea (NULL si ninguna).
 *
 * @property int $venta_id
 * @property int $producto_id
 * @property int $cantidad
 * @property string $precio_unitario
 * @property string $subtotal
 * @property int|null $promocion_id
 */
#[Fillable(['venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'subtotal', 'promocion_id'])]
class DetalleVenta extends Model
{
    protected $table = 'detalle_venta';

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

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }
}
