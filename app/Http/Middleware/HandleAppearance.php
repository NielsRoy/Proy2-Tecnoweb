<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('fontSize', $request->cookie('fontSize') ?? 'base');
        View::share('contrast', $request->cookie('contrast') ?? 'normal');
        View::share('palette', $request->cookie('palette') ?? 'adultos');
        View::share('font', $request->cookie('font') ?? 'instrument');

        return $next($request);
    }
}
