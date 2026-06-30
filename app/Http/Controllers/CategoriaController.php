<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de categorias (modulo "categorias"). Cada categoria agrupa productos y su `foto` sirve de
 * banner en la tienda; `orden` controla la secuencia de aparicion (no hay subcategorias). La foto se
 * guarda en el disco publico (storage/app/public, servido en /storage via `php artisan storage:link`).
 * Eliminar es BAJA LOGICA (activo=false) porque los productos la referencian (FK nullOnDelete). Rutas
 * protegidas con permiso:categorias,<accion>.
 */
class CategoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $categorias = Categoria::where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Categoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'descripcion' => $c->descripcion,
                'orden' => $c->orden,
                'foto_url' => $this->fotoUrl($c),
            ]);

        return Inertia::render('categorias/Index', [
            'categorias' => $categorias,
            'puedeCrear' => $request->user()->tienePermiso('categorias', 'registrar'),
            'puedeEditar' => $request->user()->tienePermiso('categorias', 'modificar'),
            'puedeEliminar' => $request->user()->tienePermiso('categorias', 'eliminar'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('categorias/Form', ['categoria' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $categoria = Categoria::create([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'orden' => $datos['orden'] ?? 0,
            'foto' => $request->hasFile('foto')
                ? $request->file('foto')->store('categorias', 'public')
                : null,
            'activo' => true,
        ]);

        Bitacora::registrar('crear', "Creó la categoría {$categoria->nombre}", 'categorias');
        $this->toastExito('Categoría creada.');

        return redirect()->route('categorias.index');
    }

    public function edit(Categoria $categoria): Response
    {
        return Inertia::render('categorias/Form', [
            'categoria' => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
                'orden' => $categoria->orden,
                'foto_url' => $this->fotoUrl($categoria),
            ],
        ]);
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $datos = $this->validar($request);

        // Si llega una foto nueva, reemplaza la anterior (borra el archivo viejo).
        if ($request->hasFile('foto')) {
            if ($categoria->foto) {
                Storage::disk('public')->delete($categoria->foto);
            }
            $categoria->foto = $request->file('foto')->store('categorias', 'public');
        }

        $categoria->fill([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'orden' => $datos['orden'] ?? 0,
        ])->save();

        Bitacora::registrar('modificar', "Modificó la categoría {$categoria->nombre}", 'categorias');
        $this->toastExito('Categoría actualizada.');

        return redirect()->route('categorias.index');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        // Baja logica (no se borra: los productos la referencian).
        $categoria->update(['activo' => false]);

        Bitacora::registrar('eliminar', "Dio de baja la categoría {$categoria->nombre}", 'categorias');
        $this->toastExito('Categoría eliminada.');

        return redirect()->route('categorias.index');
    }

    /** URL publica de la foto (respeta el subdirectorio via APP_URL), o null si no tiene. */
    private function fotoUrl(Categoria $categoria): ?string
    {
        return $categoria->foto ? Storage::disk('public')->url($categoria->foto) : null;
    }

    /**
     * Reglas de validacion (en espanol via lang/es).
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'], // max 2 MB
        ]);
    }
}
