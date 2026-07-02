<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Destino tras login/registro: la pagina fija /inicio (no el home /dashboard de Fortify).
        $this->app->singleton(
            LoginResponse::class,
            \App\Http\Responses\LoginResponse::class,
        );
        $this->app->singleton(
            RegisterResponse::class,
            \App\Http\Responses\RegisterResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureNotifications();
        $this->configureRateLimiting();

        // Un usuario ya autenticado que entra a /login o /register (middleware "guest")
        // se redirige a la pagina fija /inicio, no al /dashboard de Fortify (que ademas podria
        // estar bloqueado por el guardia permiso:dashboard,ver y dar 403).
        RedirectIfAuthenticated::redirectUsing(
            fn () => route('inicio'),
        );
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

    }

    /**
     * Personaliza el correo de "restablecer contraseña" para que salga EN ESPANOL (la notificacion
     * por defecto de Laravel lo arma desde claves en ingles y no hay lang/es.json). Se replica la
     * construccion de la URL del default (token + email como query) para conservar el subdirectorio
     * (la URL sale de APP_URL, igual que login).
     */
    private function configureNotifications(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));

            $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Restablece tu contraseña — Tienda D & D')
                ->greeting('¡Hola!')
                ->line('Recibiste este correo porque se solicitó restablecer la contraseña de tu cuenta.')
                ->action('Restablecer contraseña', $url)
                ->line("Este enlace expirará en {$minutos} minutos.")
                ->line('Si no solicitaste el cambio, ignora este correo; no se hará ninguna modificación.')
                ->salutation("Saludos,\nTienda D & D");
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}
