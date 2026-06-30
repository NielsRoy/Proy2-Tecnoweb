<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\User;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de usuarios (modulo "usuarios"). Cada usuario se crea/edita con UN rol de los
 * existentes; el rol determina su menu y permisos (ver matriz de acceso). Las rutas estan
 * protegidas con permiso:usuarios,<accion>.
 */
class UsuarioController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Rol reservado al único usuario Propietario que trae el sistema por defecto:
     * no se ofrece para crear ni para asignar a otros, y el Propietario no puede cambiar
     * de rol ni ser eliminado.
     */
    private const ROL_PROPIETARIO = 'Propietario';

    /** Listado de usuarios con su(s) rol(es) vigente(s), filtrable por rol y búsqueda (sin fechas). */
    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $usuarios = $query->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $u->roles->pluck('nombre')->values(),
            'creado' => $u->created_at?->format('d/m/Y'),
        ]);

        return Inertia::render('usuarios/Index', [
            'usuarios' => $usuarios,
            'filtros' => $filtros,
            'roles' => Rol::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            // Permisos del usuario actual: el front muestra/oculta botones segun esto
            // (el guardia del servidor es la barrera real; esto es solo UX).
            'puedeCrear' => $request->user()->tienePermiso('usuarios', 'registrar'),
            'puedeEditar' => $request->user()->tienePermiso('usuarios', 'modificar'),
            'puedeEliminar' => $request->user()->tienePermiso('usuarios', 'eliminar'),
            // Para no permitir que un usuario se elimine a si mismo desde la lista.
            'usuarioActualId' => $request->user()->id,
        ]);
    }

    /** Reporte (PDF/Excel/CSV) de los usuarios (respeta los filtros). permiso:usuarios,listar. */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $usuarios = $query->get();

        $columnas = ['Nombre', 'Correo electrónico', 'Rol(es)', 'Creado'];
        $filas = $usuarios->map(fn (User $u) => [
            $u->name,
            $u->email,
            $u->roles->pluck('nombre')->implode(', ') ?: '—',
            $u->created_at?->format('d/m/Y'),
        ])->all();

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Usuarios',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['Total: '.$usuarios->count().' usuarios', '', '', ''],
        ], 'usuarios');
    }

    /**
     * Consulta de usuarios aplicando filtros (rol + rango de fechas de creación).
     * Compartida por index y reporte.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        $filtros = [
            'rol_id' => $request->integer('rol_id') ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $query = User::query()
            ->with(['roles' => fn ($q) => $q->wherePivot('activo', true)])
            ->when($filtros['rol_id'], fn ($q, $id) => $q->whereHas(
                'roles',
                fn ($r) => $r->where('rol.id', $id)->where('usuario_rol.activo', true),
            ))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->orderBy('name');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['rol_id']) {
            $partes[] = 'Rol: '.(Rol::find($f['rol_id'])?->nombre ?? $f['rol_id']);
        }
        if ($f['desde']) {
            $partes[] = 'Creados desde: '.$f['desde'];
        }
        if ($f['hasta']) {
            $partes[] = 'Creados hasta: '.$f['hasta'];
        }
        $txt = $partes ? implode(' · ', $partes) : 'Todos los usuarios';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    /** Formulario de alta. */
    public function create(): Response
    {
        return Inertia::render('usuarios/Form', [
            'usuario' => null,
            'roles' => $this->rolesParaFormulario(null),
            'rolBloqueado' => false,
        ]);
    }

    /** Guarda un usuario nuevo y le asigna el rol elegido. */
    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, null);

        DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'], // el cast 'hashed' del modelo lo hashea
            ]);
            $this->asignarRol($usuario, (int) $datos['rol_id']);

            Bitacora::registrar(
                'crear',
                "Creó el usuario {$usuario->name} ({$usuario->email})",
                'usuarios',
            );
        });

        $this->toastExito('Usuario creado.');

        return redirect()->route('usuarios.index');
    }

    /** Formulario de edicion. */
    public function edit(User $usuario): Response
    {
        return Inertia::render('usuarios/Form', [
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol_id' => $usuario->roles()->wherePivot('activo', true)->first()?->id,
            ],
            'roles' => $this->rolesParaFormulario($usuario),
            // Al Propietario no se le puede cambiar el rol: el selector va bloqueado.
            'rolBloqueado' => $this->esPropietario($usuario),
        ]);
    }

    /** Actualiza datos del usuario; la contrasena solo cambia si se envia una nueva. */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        DB::transaction(function () use ($datos, $usuario) {
            $usuario->update([
                'name' => $datos['name'],
                'email' => $datos['email'],
            ]);

            if (! empty($datos['password'])) {
                $usuario->update(['password' => $datos['password']]);
            }

            $this->asignarRol($usuario, (int) $datos['rol_id']);

            Bitacora::registrar(
                'modificar',
                "Modificó el usuario {$usuario->name}",
                'usuarios',
            );
        });

        $this->toastExito('Usuario actualizado.');

        return redirect()->route('usuarios.index');
    }

    /** Elimina un usuario (no se permite eliminarse a si mismo). */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        abort_if(
            $usuario->id === $request->user()->id,
            403,
            'No puedes eliminar tu propia cuenta.',
        );

        abort_if(
            $this->esPropietario($usuario),
            403,
            'No se puede eliminar al usuario Propietario del sistema.',
        );

        $nombre = $usuario->name;
        $usuario->delete();

        Bitacora::registrar('eliminar', "Eliminó el usuario {$nombre}", 'usuarios');

        $this->toastExito('Usuario eliminado.');

        return redirect()->route('usuarios.index');
    }

    /**
     * Roles que puede mostrar/aceptar el formulario (única fuente de verdad para el
     * selector y la validación):
     * - Crear o editar a cualquier usuario que NO sea el Propietario → todos los roles
     *   activos MENOS Propietario (no se pueden crear más Propietarios).
     * - Editar al propio Propietario → solo su rol (el selector va bloqueado en el front).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rol>
     */
    private function rolesParaFormulario(?User $usuario)
    {
        if ($usuario && $this->esPropietario($usuario)) {
            return Rol::where('nombre', self::ROL_PROPIETARIO)->get(['id', 'nombre']);
        }

        return Rol::where('activo', true)
            ->where('nombre', '!=', self::ROL_PROPIETARIO)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /** ¿El usuario tiene el rol Propietario vigente? */
    private function esPropietario(User $usuario): bool
    {
        return $usuario->roles()
            ->where('rol.nombre', self::ROL_PROPIETARIO)
            ->wherePivot('activo', true)
            ->exists();
    }

    /**
     * Reglas de validacion (en espanol via lang/es). La contrasena es obligatoria al crear
     * y opcional al editar (vacia = no se cambia). El rol debe ser uno de los permitidos
     * para ese formulario (ver rolesParaFormulario): asi el server rechaza intentar asignar
     * Propietario aunque se manipule el request.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?User $usuario): array
    {
        $rolesPermitidos = $this->rolesParaFormulario($usuario)->pluck('id')->all();

        $reglas = [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($usuario?->id),
            'rol_id' => ['required', 'integer', Rule::in($rolesPermitidos)],
            'password' => $usuario === null
                ? $this->passwordRules()                                  // required + confirmed
                : ['nullable', 'string', Password::default(), 'confirmed'],
        ];

        return $request->validate($reglas, [], ['rol_id' => 'rol']);
    }

    /** Asigna exactamente un rol al usuario (vigente desde hoy, sin fecha de fin). */
    private function asignarRol(User $usuario, int $rolId): void
    {
        $usuario->roles()->sync([
            $rolId => ['fini' => now()->toDateString(), 'ffin' => null, 'activo' => true],
        ]);
    }
}
