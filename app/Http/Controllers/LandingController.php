<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing pública en "/": presentación comercial de la Tienda D & D para visitantes NO
 * autenticados (y también logueados). Llama a la acción de comprar con botones a
 * registro/login. NO usa la matriz ni exige auth. Su diseño es FIJO (no cambia con temas,
 * fuente, tamaño de letra ni modo oscuro): la página se autocontiene con estilos propios.
 *
 * Las imágenes se leen del disco público, carpeta "landing/" (ver imagenes/imagenes_seeder.md).
 * Si un archivo aún no existe se pasa null y la página muestra un marcador de posición, así la
 * landing funciona igual antes de que se peguen las fotos.
 */
class LandingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Landing', [
            'imagenes' => [
                'hero' => $this->url('landing/hero.jpg'),
                'abarrotes' => $this->url('landing/categoria-abarrotes.jpg'),
                'bebidas' => $this->url('landing/categoria-bebidas.jpg'),
                'limpieza' => $this->url('landing/categoria-limpieza.jpg'),
                'snacks' => $this->url('landing/categoria-snacks.jpg'),
                'promo' => $this->url('landing/promo.jpg'),
            ],
        ]);
    }

    /**
     * URL pública del archivo en el disco 'public', o null si aún no se ha subido.
     * (Storage::url() respeta el subdirectorio vía APP_URL igual que en el resto del sistema.)
     */
    private function url(string $ruta): ?string
    {
        return Storage::disk('public')->exists($ruta)
            ? Storage::disk('public')->url($ruta)
            : null;
    }
}
