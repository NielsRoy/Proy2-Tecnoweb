<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compra (CU3) — cabecera. proveedor_id es un users con rol Proveedor (validado en app).
 *
 * @property int $id
 * @property int $proveedor_id
 * @property \Illuminate\Support\Carbon $fecha_compra
 * @property string $monto_total
 * @property string $estado
 */
#[Fillable(['proveedor_id', 'fecha_compra', 'monto_total', 'estado'])]
class Compra extends Model
{
    protected $table = 'compra';

    public const ESTADO_REGISTRADA = 'registrada';

    public const ESTADO_ANULADA = 'anulada';

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'monto_total' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proveedor_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class, 'compra_id');
    }
}
