<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Categoria;
use App\Models\Producto;
use App\Support\BusquedaTabla;
use App\Support\Reporte;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de productos (CU2, modulo "productos"). El stock NO se edita aqui: nace en 0 y lo
 * mueven compras/ventas/inventario. La foto se guarda en el disco publico (storage/app/public,
 * servido en /storage via `php artisan storage:link`). Eliminar es BAJA LOGICA (activo=false) porque el
 * producto puede estar referenciado por ventas/compras (FK restrict). Rutas protegidas con
 * permiso:productos,<accion>.
 */
class ProductoController extends Controller
{
    public function index(Request $request): Response
    {
        [$filtros, $query] = $this->consultaFiltrada($request);

        $productos = $query->get()->map(fn (Producto $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'categoria' => $p->categoria?->nombre,
            'precio' => $p->precio,
            'stock' => $p->stock,
            'foto_url' => $this->fotoUrl($p),
        ]);

        return Inertia::render('productos/Index', [
            'productos' => $productos,
            'filtros' => $filtros,
            'categorias' => $this->categoriasParaFormulario(),
            'puedeCrear' => $request->user()->tienePermiso('productos', 'registrar'),
            'puedeEditar' => $request->user()->tienePermiso('productos', 'modificar'),
            'puedeEliminar' => $request->user()->tienePermiso('productos', 'eliminar'),
            'puedeReportar' => $request->user()->tienePermiso('productos', 'reportar'),
        ]);
    }

    /** Reporte (PDF/Excel/CSV) del catálogo de productos activos, respetando los filtros. */
    public function reporte(Request $request): mixed
    {
        [$filtros, $query] = $this->consultaFiltrada($request);
        $productos = $query->get();

        $columnas = ['Nombre', 'Categoría', 'Precio (Bs)', 'Stock'];
        $filas = $productos->map(fn (Producto $p) => [
            $p->nombre,
            $p->categoria?->nombre ?? 'Sin categoría',
            number_format((float) $p->precio, 2, '.', ''),
            $p->stock,
        ])->all();

        return Reporte::generar($request->string('formato')->toString(), [
            'titulo' => 'Reporte de Productos',
            'subtitulo' => $this->descripcionFiltros($filtros),
            'columnas' => $columnas,
            'filas' => $filas,
            'filaTotal' => ['Total: '.$productos->count().' productos', '', '', $productos->sum('stock')],
        ], 'productos');
    }

    /**
     * Consulta del catálogo activo aplicando filtros (categoría + rangos de precio y de stock; sin
     * fechas). Compartida por index y reporte.
     *
     * @return array{0: array<string, mixed>, 1: Builder}
     */
    private function consultaFiltrada(Request $request): array
    {
        // Lee un valor numérico opcional (vacío = sin filtro); conserva el 0 como valor válido.
        $num = fn (string $clave) => $request->input($clave) !== null && $request->input($clave) !== ''
            ? (float) $request->input($clave)
            : null;

        $filtros = [
            'q' => $request->string('q')->toString() ?: null,
            'categoria_id' => $request->integer('categoria_id') ?: null,
            'precio_min' => $num('precio_min'),
            'precio_max' => $num('precio_max'),
            'stock_min' => $num('stock_min'),
            'stock_max' => $num('stock_max'),
        ];

        $query = Producto::where('activo', true)
            ->with('categoria:id,nombre')
            ->when($filtros['q'], fn ($q, $t) => BusquedaTabla::aplicar($q, $t, ['nombre', 'descripcion']))
            ->when($filtros['categoria_id'], fn ($q, $id) => $q->where('categoria_id', $id))
            ->when($filtros['precio_min'] !== null, fn ($q) => $q->where('precio', '>=', $filtros['precio_min']))
            ->when($filtros['precio_max'] !== null, fn ($q) => $q->where('precio', '<=', $filtros['precio_max']))
            ->when($filtros['stock_min'] !== null, fn ($q) => $q->where('stock', '>=', $filtros['stock_min']))
            ->when($filtros['stock_max'] !== null, fn ($q) => $q->where('stock', '<=', $filtros['stock_max']))
            ->orderBy('nombre');

        return [$filtros, $query];
    }

    /** Texto legible de los filtros aplicados (para el subtitulo del reporte). */
    private function descripcionFiltros(array $f): string
    {
        $partes = [];
        if ($f['categoria_id']) {
            $partes[] = 'Categoría: '.(Categoria::find($f['categoria_id'])?->nombre ?? $f['categoria_id']);
        }
        if ($f['precio_min'] !== null || $f['precio_max'] !== null) {
            $partes[] = 'Precio: '.($f['precio_min'] !== null ? 'Bs '.$f['precio_min'] : '—')
                .' a '.($f['precio_max'] !== null ? 'Bs '.$f['precio_max'] : '—');
        }
        if ($f['stock_min'] !== null || $f['stock_max'] !== null) {
            $partes[] = 'Stock: '.($f['stock_min'] !== null ? (int) $f['stock_min'] : '—')
                .' a '.($f['stock_max'] !== null ? (int) $f['stock_max'] : '—');
        }
        $txt = $partes ? implode(' · ', $partes) : 'Catálogo activo completo';

        return $txt.' — Generado: '.now()->format('d/m/Y H:i');
    }

    public function create(): Response
    {
        return Inertia::render('productos/Form', [
            'producto' => null,
            'categorias' => $this->categoriasParaFormulario(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $producto = Producto::create([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'],
            'categoria_id' => $datos['categoria_id'] ?? null,
            'stock' => 0, // el stock se mueve por compras/ventas/inventario, no a mano
            'foto' => $request->hasFile('foto')
                ? $request->file('foto')->store('productos', 'public')
                : null,
            'activo' => true,
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
                'categoria_id' => $producto->categoria_id,
                'foto_url' => $this->fotoUrl($producto),
            ],
            'categorias' => $this->categoriasParaFormulario(),
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
            'categoria_id' => $datos['categoria_id'] ?? null,
        ])->save();

        Bitacora::registrar('modificar', "Modificó el producto {$producto->nombre}", 'productos');
        $this->toastExito('Producto actualizado.');

        return redirect()->route('productos.index');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        // Baja logica (no se borra: lo referencian ventas/compras).
        $producto->update(['activo' => false]);

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
     * Categorias activas para el selector del formulario.
     *
     * @return Collection<int, array{id: int, nombre: string}>
     */
    private function categoriasParaFormulario()
    {
        return Categoria::where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Categoria $c) => ['id' => $c->id, 'nombre' => $c->nombre]);
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
            'categoria_id' => ['nullable', 'integer', Rule::exists('categoria', 'id')->where('activo', true)],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'], // max 2 MB
        ], [], ['categoria_id' => 'categoría']);
    }
}
