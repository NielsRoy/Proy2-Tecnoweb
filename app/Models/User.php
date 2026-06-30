<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Roles / Control de Acceso
    // ─────────────────────────────────────────────────────────────────────────

    /** Roles asignados a este usuario (con vigencia fini/ffin en el pivote). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'user_id', 'rol_id')
            ->withPivot(['fini', 'ffin', 'est'])
            ->withTimestamps();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Negocio (cliente / proveedor) — el rol se valida en la app, no en la BD
    // ─────────────────────────────────────────────────────────────────────────

    /** Ventas en las que este usuario es el cliente. */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    /** Compras en las que este usuario es el proveedor. */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    /** Lineas del carrito de este usuario (cliente). */
    public function carrito(): HasMany
    {
        return $this->hasMany(Carrito::class, 'cliente_id');
    }

    /**
     * Scope: usuarios que TIENEN un rol vigente con ese nombre. Reutiliza la misma regla de
     * vigencia que rolesVigentes(). Lo usa Compras para listar proveedores (y a futuro
     * Ventas para clientes). Ej: User::conRolVigente('Proveedor')->get(['id', 'name']).
     */
    public function scopeConRolVigente($query, string $nombreRol)
    {
        $hoy = now()->toDateString();

        return $query->whereHas('roles', function ($q) use ($nombreRol, $hoy) {
            $q->where('rol.nombre', $nombreRol)
                ->where('rol.est', true)
                ->where('usuario_rol.est', true)
                ->where(fn ($s) => $s->whereNull('usuario_rol.fini')->orWhere('usuario_rol.fini', '<=', $hoy))
                ->where(fn ($s) => $s->whereNull('usuario_rol.ffin')->orWhere('usuario_rol.ffin', '>=', $hoy));
        });
    }

    /**
     * Roles VIGENTES hoy: rol activo, asignacion activa y dentro del rango fini..ffin
     * (cualquiera de los dos puede ser null = sin limite). Es la base de todo el control
     * de acceso: el menu y el guardia solo miran roles vigentes.
     */
    public function rolesVigentes(): BelongsToMany
    {
        $hoy = now()->toDateString();

        return $this->roles()
            ->where('rol.est', true)
            ->wherePivot('est', true)
            ->where(fn ($q) => $q->whereNull('usuario_rol.fini')->orWhere('usuario_rol.fini', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('usuario_rol.ffin')->orWhere('usuario_rol.ffin', '>=', $hoy));
    }

    /**
     * Acciones permitidas al usuario hoy: activas, de un modulo activo, otorgadas por
     * alguno de sus roles vigentes. Cada accion trae su modulo cargado (with('modulo')).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accion>
     */
    public function accionesPermitidas()
    {
        $rolIds = $this->rolesVigentes()->pluck('rol.id');

        if ($rolIds->isEmpty()) {
            return Accion::query()->whereRaw('1 = 0')->get(); // coleccion vacia tipada
        }

        return Accion::query()
            ->where('accion.est', true)
            ->whereHas('modulo', fn ($q) => $q->where('est', true))
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $rolIds))
            ->with('modulo')
            ->get();
    }

    /**
     * ¿El usuario puede ejecutar (moduloClave, accionClave)? Lo usa el guardia de rutas.
     * Ej: $user->tienePermiso('productos', 'registrar').
     */
    public function tienePermiso(string $moduloClave, string $accionClave): bool
    {
        $rolIds = $this->rolesVigentes()->pluck('rol.id');

        if ($rolIds->isEmpty()) {
            return false;
        }

        return Accion::query()
            ->where('accion.clave', $accionClave)
            ->where('accion.est', true)
            ->whereHas('modulo', fn ($q) => $q->where('clave', $moduloClave)->where('est', true))
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $rolIds))
            ->exists();
    }

    /**
     * Arbol de modulos permitidos para construir el MENU DINAMICO. Devuelve solo los
     * modulos con alguna accion permitida (o con hijos visibles), respetando padre_id y orden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function modulosPermitidos(): array
    {
        $acciones = $this->accionesPermitidas();

        if ($acciones->isEmpty()) {
            return [];
        }

        $accionesPorModulo = $acciones->groupBy('modulo_id');

        // Todos los modulos activos, agrupados por su padre (0 = raiz del menu).
        $porPadre = Modulo::where('est', true)
            ->orderBy('orden')->orderBy('id')->get()
            ->groupBy(fn (Modulo $m) => (int) ($m->padre_id ?? 0));

        // Recursion: arma cada nivel; incluye un modulo si tiene acciones o hijos visibles.
        $construir = function (int $padreId) use (&$construir, $porPadre, $accionesPorModulo): array {
            $nodos = [];

            foreach ($porPadre->get($padreId, collect()) as $modulo) {
                $hijos = $construir((int) $modulo->id);
                $accs = $accionesPorModulo->get($modulo->id);

                if (! $accs && empty($hijos)) {
                    continue; // sin permisos ni hijos visibles -> no se muestra
                }

                $nodos[] = [
                    'clave' => $modulo->clave,
                    'nombre' => $modulo->nombre,
                    'icono' => $modulo->icono,
                    'ruta' => $modulo->ruta,
                    'acciones' => $accs
                        ? $accs->map(fn (Accion $a) => [
                            'clave' => $a->clave,
                            'nombre' => $a->nombre,
                            'ruta' => $a->ruta,
                        ])->values()->all()
                        : [],
                    'hijos' => $hijos,
                ];
            }

            return $nodos;
        };

        return $construir(0);
    }
}
