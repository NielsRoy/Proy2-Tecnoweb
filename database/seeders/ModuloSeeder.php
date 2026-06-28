<?php

namespace Database\Seeders;

use App\Models\Accion;
use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModuloSeeder extends Seeder
{
    public function run(): void
    {
        // Acciones CRUD estandar que comparten la mayoria de modulos de negocio.
        $crud = [
            'listar' => 'Listar',
            'registrar' => 'Registrar',
            'modificar' => 'Modificar',
            'eliminar' => 'Eliminar',
        ];

        // Cada modulo = un item del menu. `icono` = nombre de icono lucide (lo mapea el front).
        // `ruta` = nombre de ruta Laravel (se resuelve a URL respetando el subdirectorio).
        // Las rutas de negocio aun no existen: el menu las mostrara como no-clicables hasta crearlas.
        $modulos = [
            ['clave' => 'dashboard', 'nombre' => 'Dashboard', 'icono' => 'LayoutGrid', 'ruta' => 'dashboard', 'orden' => 1, 'acciones' => ['ver' => 'Ver']],
            ['clave' => 'usuarios', 'nombre' => 'Usuarios', 'icono' => 'Users', 'ruta' => 'usuarios.index', 'orden' => 2, 'acciones' => $crud],
            ['clave' => 'productos', 'nombre' => 'Productos', 'icono' => 'Package', 'ruta' => 'productos.index', 'orden' => 3, 'acciones' => $crud],
            ['clave' => 'compras', 'nombre' => 'Compras', 'icono' => 'ShoppingCart', 'ruta' => 'compras.index', 'orden' => 4, 'acciones' => $crud],
            ['clave' => 'ventas', 'nombre' => 'Ventas', 'icono' => 'Receipt', 'ruta' => 'ventas.index', 'orden' => 5, 'acciones' => $crud],
            ['clave' => 'inventarios', 'nombre' => 'Inventarios', 'icono' => 'Boxes', 'ruta' => 'inventarios.index', 'orden' => 6, 'acciones' => $crud],
            ['clave' => 'promociones', 'nombre' => 'Promociones', 'icono' => 'BadgePercent', 'ruta' => 'promociones.index', 'orden' => 7, 'acciones' => $crud],
            ['clave' => 'pagos', 'nombre' => 'Pagos', 'icono' => 'CreditCard', 'ruta' => 'pagos.index', 'orden' => 8, 'acciones' => $crud],
            ['clave' => 'reportes', 'nombre' => 'Reportes', 'icono' => 'ChartColumn', 'ruta' => 'reportes.index', 'orden' => 9, 'acciones' => ['listar' => 'Ver reportes']],
            ['clave' => 'acceso', 'nombre' => 'Control de Acceso', 'icono' => 'ShieldCheck', 'ruta' => 'acceso.matriz', 'orden' => 10, 'acciones' => ['listar' => 'Ver matriz', 'modificar' => 'Editar matriz']],
        ];

        foreach ($modulos as $m) {
            $modulo = Modulo::updateOrCreate(['clave' => $m['clave']], [
                'nombre' => $m['nombre'],
                'icono' => $m['icono'],
                'ruta' => $m['ruta'],
                'orden' => $m['orden'],
                'padre_id' => null,
                'est' => true,
            ]);

            foreach ($m['acciones'] as $clave => $nombre) {
                Accion::updateOrCreate(
                    ['modulo_id' => $modulo->id, 'clave' => $clave],
                    ['nombre' => $nombre, 'est' => true],
                );
            }
        }
    }
}
