<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Regla de password: SOLO mínimo 8 caracteres, en local Y en producción. Es una decisión
        // deliberada para la defensa del proyecto (el docente crea usuarios e inicia sesión en vivo):
        // una password compleja quitaría tiempo. Si algún día se quisiera endurecer en producción,
        // aquí es el único punto a tocar (encadenar ->mixedCase()->numbers()->symbols()->uncompromised()).
        Password::defaults(fn (): Password => Password::min(8));
    }
}
