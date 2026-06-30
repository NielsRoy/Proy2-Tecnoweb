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

    // Tipos de movimiento (direccion del stock).
    public const INGRESO = 'ingreso';

    public const SALIDA = 'salida';

    // Motivos del movimiento (de donde viene).
    public const MOTIVO_COMPRA = 'compra';

    public const MOTIVO_VENTA = 'venta';

    public const MOTIVO_CORRECCION = 'correccion';

    public const MOTIVO_AJUSTE = 'ajuste';

    public const MOTIVO_INICIAL = 'inicial';

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

    /**
     * UNICO punto que mueve stock: ajusta producto.stock y escribe el asiento append-only.
     * Lo usan Inventario (ajuste), Compras (ingreso) y, a futuro, Ventas (salida).
     *
     * El llamador DEBE envolver esto en DB::transaction (una compra mueve varias lineas).
     * La validacion amigable de "stock suficiente" la hacen los controladores antes de
     * llegar aqui; esta es la ultima salvaguarda dentro de la transaccion.
     *
     * @param  string  $tipo    self::INGRESO | self::SALIDA
     * @param  int     $cantidad cantidad SIEMPRE positiva (la direccion la da $tipo)
     * @param  string  $motivo  self::MOTIVO_*
     * @param  string|null  $fecha fecha del movimiento (Y-m-d); hoy si es null
     */
    public static function registrarMovimiento(
        Producto $producto,
        string $tipo,
        int $cantidad,
        string $motivo,
        ?string $fecha = null,
    ): self {
        if ($cantidad < 1) {
            throw new \InvalidArgumentException('La cantidad del movimiento debe ser positiva.');
        }

        if ($tipo === self::SALIDA && $producto->stock < $cantidad) {
            throw new \RuntimeException("Stock insuficiente de «{$producto->nombre}» para la salida.");
        }

        $producto->stock += $tipo === self::INGRESO ? $cantidad : -$cantidad;
        $producto->save();

        return self::create([
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'tipo_movimiento' => $tipo,
            'fecha_movimiento' => $fecha ?? today()->toDateString(),
            'motivo' => $motivo,
        ]);
    }
}
