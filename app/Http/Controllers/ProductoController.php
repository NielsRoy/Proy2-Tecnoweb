<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de productos (CU2, modulo "productos"). El stock NO se edita aqui: nace en 0 y lo
 * mueven compras/ventas/inventario. La foto se guarda en el disco publico (storage/app/public,
 * servido en /storage via `php artisan storage:link`). Eliminar es BAJA LOGICA (est=false) porque el
 * producto puede estar referenciado por ventas/compras (FK restrict). Rutas protegidas con
 * permiso:productos,<accion>.
 */
class ProductoController extends Controller
{
    public function index(Request $request): Response
    {
        $productos = Producto::where('est', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'precio' => $p->precio,
                'stock' => $p->stock,
                'foto_url' => $this->fotoUrl($p),
            ]);

        return Inertia::render('productos/Index', [
            'productos' => $productos,
            'puedeCrear' => $request->user()->tienePermiso('productos', 'registrar'),
            'puedeEditar' => $request->user()->tienePermiso('productos', 'modificar'),
            'puedeEliminar' => $request->user()->tienePermiso('productos', 'eliminar'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('productos/Form', ['producto' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $producto = Producto::create([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'],
            'stock' => 0, // el stock se mueve por compras/ventas/inventario, no a mano
            'foto' => $request->hasFile('foto')
                ? $request->file('foto')->store('productos', 'public')
                : null,
            'est' => true,
        ]);

        Bitacora::registrar('crear', "Creó el producto {$producto->nombre}", 'productos');
        $this->toastExito('Producto creado.');

        return redirect()->route('productos.index');
    }

    public function edit(Producto $producto): Response
    {
        return Inertia::render('productos/Form', [
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'descripcion' => $producto->descripcion,
                'precio' => $producto->precio,
                'foto_url' => $this->fotoUrl($producto),
            ],
        ]);
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $datos = $this->validar($request);

        // Si llega una foto nueva, reemplaza la anterior (borra el archivo viejo).
        if ($request->hasFile('foto')) {
            if ($producto->foto) {
                Storage::disk('public')->delete($producto->foto);
            }
            $producto->foto = $request->file('foto')->store('productos', 'public');
        }

        $producto->fill([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'],
        ])->save();

        Bitacora::registrar('modificar', "Modificó el producto {$producto->nombre}", 'productos');
        $this->toastExito('Producto actualizado.');

        return redirect()->route('productos.index');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        // Baja logica (no se borra: lo referencian ventas/compras).
        $producto->update(['est' => false]);

        Bitacora::registrar('eliminar', "Dio de baja el producto {$producto->nombre}", 'productos');
        $this->toastExito('Producto eliminado.');

        return redirect()->route('productos.index');
    }

    /** URL publica de la foto (respeta el subdirectorio via APP_URL), o null si no tiene. */
    private function fotoUrl(Producto $producto): ?string
    {
        return $producto->foto ? Storage::disk('public')->url($producto->foto) : null;
    }

    /**
     * Reglas de validacion (en espanol via lang/es). El stock no se valida: no se edita aqui.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'precio' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'], // max 2 MB
        ]);
    }
}
