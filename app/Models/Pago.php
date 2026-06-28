<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago (CU7): cada fila es una CUOTA del cronograma de una venta. payment_number /
 * pagofacil_transaction_id son para el pago por QR (requisito #10), NULL hasta implementarlo.
 *
 * @property int $id
 * @property int $venta_id
 * @property int $numero_cuota
 * @property string $monto
 * @property \Illuminate\Support\Carbon $fecha_vencimiento
 * @property \Illuminate\Support\Carbon|null $fecha_pago
 * @property string|null $metodo
 * @property string $estado
 * @property string|null $payment_number
 * @property string|null $pagofacil_transaction_id
 */
#[Fillable(['venta_id', 'numero_cuota', 'monto', 'fecha_vencimiento', 'fecha_pago', 'metodo', 'estado', 'payment_number', 'pagofacil_transaction_id'])]
class Pago extends Model
{
    protected $table = 'pago';

    protected function casts(): array
    {
        return [
            'numero_cuota' => 'integer',
            'monto' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
