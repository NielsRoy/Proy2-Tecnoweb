<?php

namespace Database\Seeders;

use App\Models\Accion;
use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModuloSeeder extends Seeder
{
    public function run(): void
    {
        // Acciones CRUD completo (solo para los modulos que de verdad las tienen todas).
        $crud = [
            'listar' => 'Listar',
            'registrar' => 'Registrar',
            'modificar' => 'Modificar',
            'eliminar' => 'Eliminar',
        ];

        // Accion opcional para los modulos que exponen reportes (PDF/Excel/CSV): controla que se
        // muestren los botones de exportar (los filtros siguen apareciendo con solo "listar").
        $reportar = ['reportar' => 'Generar reportes'];

        // Cada modulo = un item del menu. `icono` = nombre de icono lucide (lo mapea el front).
        // `ruta` = nombre de ruta Laravel (se resuelve a URL respetando el subdirectorio).
        // Las `acciones` deben COINCIDIR con las rutas reales de cada controlador (lo que el
        // middleware `permiso:modulo,accion` exige). Por eso varios modulos NO tienen las 4:
        // compras/ventas no se modifican (se anulan -> "eliminar"); inventarios es append-only
        // (solo listar/registrar); pagos solo lista y cobra (registrar).
        $modulos = [
            ['clave' => 'dashboard', 'nombre' => 'Dashboard', 'icono' => 'LayoutGrid', 'ruta' => 'dashboard', 'orden' => 1, 'acciones' => ['ver' => 'Ver']],
            ['clave' => 'usuarios', 'nombre' => 'Usuarios', 'icono' => 'Users', 'ruta' => 'usuarios.index', 'orden' => 2, 'acciones' => $crud + $reportar],
            ['clave' => 'productos', 'nombre' => 'Productos', 'icono' => 'Package', 'ruta' => 'productos.index', 'orden' => 3, 'acciones' => $crud + $reportar],
            ['clave' => 'categorias', 'nombre' => 'Categorías', 'icono' => 'Tags', 'ruta' => 'categorias.index', 'orden' => 4, 'acciones' => $crud],
            ['clave' => 'compras', 'nombre' => 'Compras', 'icono' => 'ShoppingCart', 'ruta' => 'compras.index', 'orden' => 5, 'acciones' => ['listar' => 'Listar', 'registrar' => 'Registrar', 'eliminar' => 'Anular'] + $reportar],
            ['clave' => 'ventas', 'nombre' => 'Ventas', 'icono' => 'Receipt', 'ruta' => 'ventas.index', 'orden' => 6, 'acciones' => ['listar' => 'Listar', 'registrar' => 'Registrar', 'eliminar' => 'Anular'] + $reportar],
            ['clave' => 'inventarios', 'nombre' => 'Inventarios', 'icono' => 'Boxes', 'ruta' => 'inventarios.index', 'orden' => 7, 'acciones' => ['listar' => 'Listar', 'registrar' => 'Registrar'] + $reportar],
            ['clave' => 'promociones', 'nombre' => 'Promociones', 'icono' => 'BadgePercent', 'ruta' => 'promociones.index', 'orden' => 8, 'acciones' => $crud + $reportar],
            ['clave' => 'pagos', 'nombre' => 'Pagos', 'icono' => 'CreditCard', 'ruta' => 'pagos.index', 'orden' => 9, 'acciones' => ['listar' => 'Listar', 'registrar' => 'Registrar']],
            ['clave' => 'reportes', 'nombre' => 'Reportes', 'icono' => 'ChartColumn', 'ruta' => 'reportes.index', 'orden' => 10, 'acciones' => ['listar' => 'Ver reportes']],
            ['clave' => 'bitacora', 'nombre' => 'Bitácora', 'icono' => 'ScrollText', 'ruta' => 'bitacora.index', 'orden' => 11, 'acciones' => ['listar' => 'Ver bitácora'] + $reportar],
            ['clave' => 'acceso', 'nombre' => 'Control de Acceso', 'icono' => 'ShieldCheck', 'ruta' => 'acceso.matriz', 'orden' => 12, 'acciones' => ['listar' => 'Ver matriz', 'modificar' => 'Editar matriz']],
        ];

        foreach ($modulos as $m) {
            $modulo = Modulo::updateOrCreate(['clave' => $m['clave']], [
                'nombre' => $m['nombre'],
                'icono' => $m['icono'],
                'ruta' => $m['ruta'],
                'orden' => $m['orden'],
                'padre_id' => null,
                'activo' => true,
            ]);

            foreach ($m['acciones'] as $clave => $nombre) {
                Accion::updateOrCreate(
                    ['modulo_id' => $modulo->id, 'clave' => $clave],
                    ['nombre' => $nombre, 'activo' => true],
                );
            }
        }
    }
}
