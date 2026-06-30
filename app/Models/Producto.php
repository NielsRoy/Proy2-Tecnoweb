<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto del catalogo. `stock` es denormalizado (lo mueven compra/venta/inventario).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $precio
 * @property int $stock
 * @property string|null $foto
 * @property int|null $categoria_id
 * @property bool $est
 */
#[Fillable(['nombre', 'descripcion', 'precio', 'stock', 'foto', 'categoria_id', 'est'])]
class Producto extends Model
{
    protected $table = 'producto';

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'est' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function promociones(): HasMany
    {
        return $this->hasMany(Promocion::class, 'producto_id');
    }

    public function detalleVentas(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'producto_id');
    }

    public function detalleCompras(): HasMany
    {
        return $this->hasMany(DetalleCompra::class, 'producto_id');
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class, 'producto_id');
    }
}
