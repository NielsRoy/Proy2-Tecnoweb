<?php

namespace App\Http\Middleware;

use App\Models\VisitaPagina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Contador de visitas por pagina (requisito #7). Inertia llama a share() una
        // vez por render de pagina; se cuenta solo en GET reales (no en partial reloads,
        // ni en POST/redirects de formularios), una visita = una vista de pagina.
        $visitasPagina = null;
        if ($request->isMethod('GET') && ! $request->headers->has('X-Inertia-Partial-Data')) {
            $ruta = $request->route()?->getName() ?? $request->path();
            $visitasPagina = VisitaPagina::registrar($ruta);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Nombres de los roles vigentes del usuario (para mostrar el rol en la UI).
                'roles' => $request->user()
                    ? $request->user()->rolesVigentes()->pluck('nombre')->values()
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'visitasPagina' => $visitasPagina,
            // Menu dinamico: arbol de modulos permitidos al usuario logueado (vacio si no hay sesion).
            'menu' => $this->menuUsuario($request),
        ];
    }

    /**
     * Construye el menu del usuario autenticado a partir de sus modulos permitidos,
     * resolviendo cada nombre de ruta a una URL (que respeta el subdirectorio via APP_URL).
     * Si la ruta aun no existe (CU sin construir), href = null y el front la muestra no-clicable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function menuUsuario(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        return $this->resolverHrefs($user->modulosPermitidos());
    }

    /**
     * Recorre el arbol del menu y agrega `href` (URL resuelta) a cada modulo.
     *
     * @param  array<int, array<string, mixed>>  $nodos
     * @return array<int, array<string, mixed>>
     */
    private function resolverHrefs(array $nodos): array
    {
        return array_map(function (array $nodo) {
            $nodo['href'] = $nodo['ruta'] && Route::has($nodo['ruta'])
                ? route($nodo['ruta'], absolute: false)
                : null;
            $nodo['hijos'] = $this->resolverHrefs($nodo['hijos']);

            return $nodo;
        }, $nodos);
    }
}
