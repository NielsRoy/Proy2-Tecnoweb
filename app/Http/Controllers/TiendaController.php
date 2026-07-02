<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Promocion;
use App\Support\BusquedaTabla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tienda autoservicio en "/inicio": catalogo de productos para que el usuario logueado compre por su
 * cuenta (carrito + checkout en CarritoController). Es de LIBRE ACCESO para todo autenticado (solo
 * 'auth', fuera de la matriz). Muestra foto, precio y promo vigente de cada producto.
 */
class TiendaController extends Controller
{
    public function index(Request $request): Response
    {
        // Filtro por categoria via query param (?categoria=ID) -> NO crea URLs nuevas (sigue siendo /inicio).
        $categoriaId = $request->integer('categoria') ?: null;
        // Búsqueda de texto (buscador global): filtra productos por nombre/descripción. Se combina con la categoría.
        $q = $request->string('q')->toString() ?: null;

        // Categorias activas con productos visibles (para los botones de filtro y el banner activo).
        $categorias = Categoria::where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Categoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'descripcion' => $c->descripcion,
                'foto_url' => $c->foto ? Storage::disk('public')->url($c->foto) : null,
            ]);

        // Solo dejamos activa una categoria que exista (evita estado raro si el id no es valido).
        $categoriaActiva = $categorias->firstWhere('id', $categoriaId) ? $categoriaId : null;

        $promosVigentes = Promocion::vigente()->get()->keyBy('producto_id');

        $productos = Producto::where('activo', true)
            ->when($categoriaActiva, fn ($query, $id) => $query->where('categoria_id', $id))
            ->when($q, fn ($query, $t) => BusquedaTabla::aplicar($query, $t, ['nombre', 'descripcion']))
            ->orderBy('nombre')->get()
            ->map(function (Producto $p) use ($promosVigentes) {
                $promo = $promosVigentes->get($p->id);

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'foto_url' => $p->foto ? Storage::disk('public')->url($p->foto) : null,
                    'precio' => (float) $p->precio,
                    'precio_final' => $promo ? $promo->precioConDescuento((float) $p->precio) : (float) $p->precio,
                    'promocion_nombre' => $promo?->nombre,
                    'promocion_tipo' => $promo?->tipo_descuento,
                    'promocion_valor' => $promo ? (float) $promo->valor : null,
                    'stock' => $p->stock,
                ];
            });

        // Total de unidades en el carrito del usuario (para el badge del enlace al carrito).
        $carritoCount = (int) Carrito::where('cliente_id', $request->user()->id)->sum('cantidad');

        return Inertia::render('Inicio', [
            'productos' => $productos,
            'carritoCount' => $carritoCount,
            'categorias' => $categorias,
            'categoriaActiva' => $categoriaActiva,
            // El buscador global detecta soporte de filtro por la clave `q` en `filtros`.
            'filtros' => ['q' => $q],
        ]);
    }
}
