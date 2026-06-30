<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta (CU4) — cabecera. cliente_id es un users con rol Cliente (validado en app).
 * El plan de pago vive en la venta; la promo se aplica por linea (detalle_venta).
 *
 * @property int $id
 * @property int $cliente_id
 * @property \Illuminate\Support\Carbon $fecha_venta
 * @property string $direccion_envio
 * @property string $monto_total
 * @property string $tipo_pago
 * @property int $numero_cuotas
 * @property string $estado_pago
 * @property string $estado
 */
#[Fillable(['cliente_id', 'fecha_venta', 'direccion_envio', 'monto_total', 'tipo_pago', 'numero_cuotas', 'estado_pago', 'estado'])]
class Venta extends Model
{
    protected $table = 'venta';

    // estado (de la venta, no del pago)
    public const ESTADO_REGISTRADA = 'registrada';

    public const ESTADO_ANULADA = 'anulada';

    // tipo_pago
    public const TIPO_CONTADO = 'contado';

    public const TIPO_CREDITO = 'credito';

    // estado_pago
    public const PAGO_PENDIENTE = 'pendiente';

    public const PAGO_PAGADA = 'pagada';

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'monto_total' => 'decimal:2',
            'numero_cuotas' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'venta_id');
    }
}
