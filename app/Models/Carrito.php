<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carrito del cliente — intermedia directa con PK COMPUESTA (cliente_id, producto_id).
 * Persistente: el carrito sobrevive entre dispositivos/sesiones (un carrito por cliente).
 * Ver nota de DetalleVenta sobre la PK compuesta en Eloquent.
 *
 * @property int $cliente_id
 * @property int $producto_id
 * @property int $cantidad
 */
#[Fillable(['cliente_id', 'producto_id', 'cantidad'])]
class Carrito extends Model
{
    protected $table = 'carrito';

    public $incrementing = false;

    protected $primaryKey = null;

    protected function casts(): array
    {
        return ['cantidad' => 'integer'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
