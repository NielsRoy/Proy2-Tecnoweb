<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

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

    /**
     * URL de aterrizaje tras iniciar sesion (path relativo, respeta el subdirectorio).
     * Devuelve la primera ruta navegable del menu del usuario (ya ordenado por orden);
     * si ningun modulo tiene pagina construida (o no tiene modulos), cae a la
     * configuracion de perfil como refugio para que igual pueda entrar a configurarse.
     */
    public function urlInicio(): string
    {
        foreach ($this->modulosPermitidos() as $modulo) {
            if ($url = $this->primeraRutaNavegable($modulo)) {
                return $url;
            }
        }

        return route('profile.edit', absolute: false);
    }

    /**
     * Ruta navegable del modulo (si su ruta existe), si no busca recursivamente en sus hijos.
     * Mismo criterio que HandleInertiaRequests::resolverHrefs() (Route::has + route(absolute:false)).
     *
     * @param  array<string, mixed>  $modulo
     */
    private function primeraRutaNavegable(array $modulo): ?string
    {
        if (! empty($modulo['ruta']) && Route::has($modulo['ruta'])) {
            return route($modulo['ruta'], absolute: false);
        }

        foreach ($modulo['hijos'] ?? [] as $hijo) {
            if ($url = $this->primeraRutaNavegable($hijo)) {
                return $url;
            }
        }

        return null;
    }
}
