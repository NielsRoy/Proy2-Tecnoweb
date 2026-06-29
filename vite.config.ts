import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv, type Plugin } from 'vite';

/**
 * Fix de fuentes self-hosted en subdirectorio.
 *
 * Las fuentes (Bunny, via laravel-vite-plugin) generan un CSS cuyos @font-face llevan
 * `src: url("/build/assets/...")` ROOT-RELATIVE HARDCODEADO (ver
 * node_modules/laravel-vite-plugin/dist/index.js → `/${buildDirectory}/${fileName}`), ignorando
 * ASSET_URL. Y la directiva Blade `@fonts` **inyecta ese CSS INLINE** en el <head> de la pagina
 * (Illuminate\Foundation\ViteFonts::readStyleFile), no como un <link> a un archivo. Por eso esas
 * url() se resuelven contra la URL de la PAGINA: en un subdirectorio (.../proyecto3/...) los .woff2
 * se piden a la raiz del dominio → 404 → la fuente cae al fallback (por eso "cambiar de fuente" no
 * se notaba en produccion).
 *
 * Solucion: anteponer el SUBPATH de ASSET_URL a esas url() (igual que Wayfinder/asset hornean el
 * subpath), dejando `/inf513/grupo25sa/proyecto3/build/assets/...`. En build local (sin ASSET_URL)
 * el subpath es vacio → queda `/build/assets/...` (correcto al servir en la raiz). Tambien se
 * reescribe el `fonts-manifest.json` por si se usa `@fonts('alias')` (familyStyles/variables inline).
 * Debe ir como ULTIMO plugin con enforce:'post' (corre despues de que `laravel:fonts` emite el CSS).
 */
function fontsSubpathUrls(basePath: string): Plugin {
    return {
        name: 'fonts-subpath-urls',
        enforce: 'post',
        generateBundle(_options, bundle) {
            if (basePath === '') {
                return;
            }

            for (const file of Object.values(bundle)) {
                if (file.type !== 'asset' || typeof file.source !== 'string') {
                    continue;
                }

                const esFontsCss = file.fileName.endsWith('.css') && file.fileName.includes('fonts');
                const esFontsManifest = file.fileName.endsWith('fonts-manifest.json');

                if (esFontsCss || esFontsManifest) {
                    file.source = file.source.replaceAll('/build/assets/', `${basePath}/build/assets/`);
                }
            }
        },
    };
}

export default defineConfig(({ mode }) => {
    // Subpath del subdirectorio en produccion (de ASSET_URL); '' en local. loadEnv con prefijo ''
    // lee tanto el .env activo (metodo "swap") como las env vars del proceso ($env:ASSET_URL).
    const env = loadEnv(mode, process.cwd(), '');
    const assetUrl = env.ASSET_URL ?? '';
    let basePath = '';
    try {
        basePath = assetUrl ? new URL(assetUrl).pathname.replace(/\/$/, '') : '';
    } catch {
        basePath = assetUrl.replace(/\/$/, ''); // por si ASSET_URL fuese solo un path
    }

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                    // Fuentes de los temas por edad: Niños = Fredoka, Jóvenes = Poppins.
                    bunny('Fredoka', {
                        weights: [400, 500, 600],
                    }),
                    bunny('Poppins', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
            fontsSubpathUrls(basePath),
        ],
    };
});
