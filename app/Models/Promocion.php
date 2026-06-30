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

    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_MONTO = 'monto';

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

    /** Promos VIGENTES hoy: activas y con la fecha de hoy dentro del rango. */
    public function scopeVigente($query)
    {
        $hoy = today()->toDateString();

        return $query->where('est', true)
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy);
    }

    /** Precio unitario con el descuento aplicado (nunca negativo), redondeado a 2 decimales. */
    public function precioConDescuento(float $precioBase): float
    {
        $valor = (float) $this->valor;

        $precio = $this->tipo_descuento === self::TIPO_PORCENTAJE
            ? $precioBase * (1 - $valor / 100)
            : $precioBase - $valor;

        return round(max(0, $precio), 2);
    }
}
