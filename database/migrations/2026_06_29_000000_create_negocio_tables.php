<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Primera version del esquema de NEGOCIO (Tienda D & D). Adapta el sistema viejo (por correo,
 * una tabla `usuario` con CI y ventas/compras de un solo producto) al web:
 *  - cliente y proveedor son `users` + rol (FK a users.id), no una tabla aparte.
 *  - ventas y compras son MULTI-producto: cabecera (venta/compra) + detalle (PK compuesta).
 *  - producto con `foto`; carrito persistente del cliente (PK compuesta cliente+producto).
 * Nombres en espanol y SINGULAR. Los enums se guardan como string y se validan en la app.
 * La logica de negocio (stock, promos, credito/cuotas, etc.) se implementa luego, CU por CU.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catalogo de productos. `stock` es denormalizado (lo mueven compra/venta/inventario,
        // con `inventario` como libro mayor). `est=false` = baja logica (lo referencian ventas).
        Schema::create('producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->integer('stock')->default(0);
            $table->string('foto')->nullable();        // ruta del archivo en storage (subida futura)
            $table->boolean('est')->default(true);     // activo en catalogo / soft-delete
            $table->timestamps();
        });

        // Promocion (CU6): descuento por producto. `est` cumple el rol del viejo `activo`
        // (soft-delete: las ventas que la aplicaron la siguen referenciando).
        Schema::create('promocion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('producto')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('tipo_descuento');          // 'porcentaje' | 'monto'
            $table->decimal('valor', 10, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('est')->default(true);
            $table->timestamps();
        });

        // Compra (CU3) — cabecera. proveedor_id es un users con rol Proveedor (validado en app).
        Schema::create('compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_compra');
            $table->decimal('monto_total', 10, 2);
            $table->string('estado')->default('registrada'); // registrada | anulada (anular revierte stock)
            $table->timestamps();
        });

        // Detalle de compra — intermedia con PK COMPUESTA (compra_id, producto_id).
        Schema::create('detalle_compra', function (Blueprint $table) {
            $table->foreignId('compra_id')->constrained('compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('producto')->restrictOnDelete();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);  // costo unitario de compra
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->primary(['compra_id', 'producto_id']);
        });

        // Venta (CU4) — cabecera. cliente_id es un users con rol Cliente (validado en app).
        // El plan de pago vive en la venta; la promo se aplica POR LINEA (en detalle_venta).
        Schema::create('venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_venta');
            $table->text('direccion_envio');
            $table->decimal('monto_total', 10, 2);
            $table->string('tipo_pago')->default('contado');     // 'contado' | 'credito'
            $table->integer('numero_cuotas')->default(1);        // 1 contado, >=2 credito
            $table->string('estado_pago')->default('pendiente'); // 'pendiente' | 'pagada'
            $table->timestamps();
        });

        // Detalle de venta — intermedia con PK COMPUESTA (venta_id, producto_id).
        // promocion_id = promo aplicada a esa linea (NULL si ninguna); base del reporte CU8.
        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->foreignId('venta_id')->constrained('venta')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('producto')->restrictOnDelete();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);   // precio ya con descuento
            $table->decimal('subtotal', 10, 2);
            $table->foreignId('promocion_id')->nullable()->constrained('promocion')->nullOnDelete();
            $table->timestamps();

            $table->primary(['venta_id', 'producto_id']);
        });

        // Inventario (CU5): libro de movimientos append-only. `motivo` indica el origen.
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('producto')->restrictOnDelete();
            $table->integer('cantidad');
            $table->string('tipo_movimiento');                 // 'ingreso' | 'salida'
            $table->date('fecha_movimiento');
            $table->string('motivo')->default('ajuste');       // compra|venta|correccion|ajuste|inicial
            $table->timestamps();
        });

        // Pago (CU7): cada fila = una CUOTA del cronograma de una venta.
        Schema::create('pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('venta')->cascadeOnDelete();
            $table->integer('numero_cuota');
            $table->decimal('monto', 10, 2);
            $table->date('fecha_vencimiento');
            $table->date('fecha_pago')->nullable();            // NULL hasta que se paga
            $table->string('metodo')->nullable();              // efectivo|transferencia|tarjeta|qr
            $table->string('estado')->default('pendiente');    // 'pendiente' | 'pagado'
            // Pago electronico / QR (requisito #10): nullable hasta implementarlo (evita re-migrar).
            $table->string('payment_number')->nullable()->unique();   // UUID = companyTransactionId
            $table->string('pagofacil_transaction_id')->nullable();
            $table->timestamps();
        });

        // Carrito del cliente — intermedia directa con PK COMPUESTA (cliente_id, producto_id).
        // Persistente: el carrito sobrevive entre dispositivos/sesiones (un carrito por cliente).
        Schema::create('carrito', function (Blueprint $table) {
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('producto')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->timestamps();

            $table->primary(['cliente_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        // Orden inverso por las llaves foraneas.
        Schema::dropIfExists('carrito');
        Schema::dropIfExists('pago');
        Schema::dropIfExists('inventario');
        Schema::dropIfExists('detalle_venta');
        Schema::dropIfExists('venta');
        Schema::dropIfExists('detalle_compra');
        Schema::dropIfExists('compra');
        Schema::dropIfExists('promocion');
        Schema::dropIfExists('producto');
    }
};
