<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pago (CU7): cada fila es una CUOTA del cronograma de una venta. payment_number /
 * pagofacil_transaction_id son para el pago por QR (requisito #10), NULL hasta implementarlo.
 *
 * @property int $id
 * @property int $venta_id
 * @property int $numero_cuota
 * @property string $monto
 * @property Carbon $fecha_vencimiento
 * @property Carbon|null $fecha_pago
 * @property string|null $metodo
 * @property string $estado
 * @property string|null $payment_number
 * @property string|null $pagofacil_transaction_id
 */
#[Fillable(['venta_id', 'numero_cuota', 'monto', 'fecha_vencimiento', 'fecha_pago', 'metodo', 'estado', 'payment_number', 'pagofacil_transaction_id'])]
class Pago extends Model
{
    protected $table = 'pago';

    // metodo de pago (qr queda para el requisito #10, aun no implementado)
    public const METODO_EFECTIVO = 'efectivo';

    public const METODO_TRANSFERENCIA = 'transferencia';

    public const METODO_TARJETA = 'tarjeta';

    public const METODO_QR = 'qr';

    // estado de la cuota
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PAGADO = 'pagado';

    protected function casts(): array
    {
        return [
            'numero_cuota' => 'integer',
            'monto' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'datetime', // guarda fecha + hora del pago
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /**
     * Salda una cuota: la marca pagada hoy con el metodo dado. Si era la ultima cuota pendiente
     * de su venta, deja la venta como 'pagada'. Lo usan la venta al contado (paga al registrarse)
     * y el modulo Pagos (cobro de cuotas a credito). DEBE llamarse dentro de una transaccion.
     */
    public static function saldar(self $cuota, string $metodo): void
    {
        $cuota->update([
            'fecha_pago' => now(), // fecha + hora exactas del pago
            'metodo' => $metodo,
            'estado' => self::ESTADO_PAGADO,
        ]);

        $quedanPendientes = self::where('venta_id', $cuota->venta_id)
            ->where('estado', self::ESTADO_PENDIENTE)
            ->exists();

        if (! $quedanPendientes) {
            $cuota->venta()->update(['estado_pago' => Venta::PAGO_PAGADA]);
        }
    }
}
