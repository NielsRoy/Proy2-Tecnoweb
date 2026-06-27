<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script: aplica el tema oscuro de inmediato (evita parpadeo) según la preferencia guardada --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                let isDark = false;

                if (appearance === 'system') {
                    isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                } else if (appearance === 'schedule') {
                    // Modo 'Horario': oscuro de 19:00 a 06:59 según la hora del dispositivo.
                    const hour = new Date().getHours();
                    isDark = hour >= 19 || hour < 7;
                } else {
                    isDark = appearance === 'dark';
                }

                if (isDark) {
                    document.documentElement.classList.add('dark');
                }

                // Accesibilidad: aplica el tamaño de letra guardado antes de pintar (evita salto).
                const fontSize = '{{ $fontSize ?? "base" }}';
                document.documentElement.dataset.fontSize = fontSize;

                // Accesibilidad: aplica el alto contraste guardado antes de pintar.
                const contrast = '{{ $contrast ?? "normal" }}';
                document.documentElement.dataset.contrast = contrast;

                // Tema por edad: aplica paleta de colores y fuente antes de pintar.
                document.documentElement.dataset.palette = '{{ $palette ?? "adultos" }}';
                document.documentElement.dataset.font = '{{ $font ?? "instrument" }}';
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
