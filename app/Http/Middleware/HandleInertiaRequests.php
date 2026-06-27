<?php

namespace App\Http\Middleware;

use App\Models\VisitaPagina;
use Illuminate\Http\Request;
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
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'visitasPagina' => $visitasPagina,
        ];
    }
}
