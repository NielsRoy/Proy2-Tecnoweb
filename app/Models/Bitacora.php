<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Bitacora (auditoria): un registro = un evento del sistema (login ok/fallido, logout,
 * operaciones de negocio). Append-only. Ver migracion ..._create_bitacora_table.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $accion
 * @property string|null $modulo
 * @property string $descripcion
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'accion', 'modulo', 'descripcion', 'ip', 'user_agent'])]
class Bitacora extends Model
{
    protected $table = 'bitacora';

    // Append-only: la tabla no tiene updated_at (solo created_at).
    public const UPDATED_AT = null;

    /** Usuario que realizo la operacion (null si no aplica, p. ej. login fallido). */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Registra un evento en la bitacora. Toma usuario/ip/user_agent del request actual,
     * asi las llamadas quedan minimas. `userId` permite forzar el usuario (los listeners de
     * auth lo pasan desde el evento; en un login fallido va null).
     */
    public static function registrar(
        string $accion,
        string $descripcion,
        ?string $modulo = null,
        ?int $userId = null,
    ): self {
        return static::create([
            'user_id' => $userId ?? Auth::id(),
            'accion' => $accion,
            'modulo' => $modulo,
            'descripcion' => $descripcion,
            'ip' => Request::ip(),
            'user_agent' => Str::limit((string) Request::userAgent(), 250, ''),
        ]);
    }
}
