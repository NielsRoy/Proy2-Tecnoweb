<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas del sistema de Roles / Menu Dinamico / Matriz de Acceso.
 *
 * Una sola fuente de verdad alimenta: (a) el menu del usuario, (b) la matriz del
 * admin y (c) el guardia de rutas. Nombres en espanol (alfabeto ingles, sin tildes/n),
 * salvo la tabla `users` del starter kit que se deja como esta.
 *
 * Modelo (inspirado en el del docente: persona/grupo/permiso/modulo/accion):
 *   users ──(usuario_rol, con vigencia)── rol ──(rol_accion = MATRIZ)── accion ──< modulo
 */
return new class extends Migration
{
    public function up(): void
    {
        // Un rol agrupa permisos (ej. Administrador, Vendedor, Cliente, Proveedor).
        Schema::create('rol', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('est')->default(true); // activo/inactivo (patron del docente)
            $table->timestamps();
        });

        // Un modulo = un item del menu / recurso del sistema (ej. Productos, Ventas).
        // padre_id permite submenus (modulo agrupador -> hijos).
        Schema::create('modulo', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();                // identificador estable (ej. "productos") que usa el guardia
            $table->string('nombre');                         // etiqueta visible en el menu (ej. "Productos")
            $table->string('descripcion')->nullable();
            $table->string('icono')->nullable();              // nombre del icono lucide (ej. "Package")
            $table->string('ruta')->nullable();               // nombre de ruta Laravel base (ej. "productos.index")
            $table->unsignedInteger('orden')->default(0);     // orden de aparicion en el menu
            $table->foreignId('padre_id')->nullable()         // self-FK: submenu
                ->constrained('modulo')->nullOnDelete();
            $table->boolean('est')->default(true);
            $table->timestamps();
        });

        // Una accion = sub-opcion de un modulo (registrar/modificar/eliminar/listar).
        // `clave` es el identificador estable que usa el guardia (permiso:modulo,clave).
        Schema::create('accion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulo')->cascadeOnDelete();
            $table->string('nombre');                         // etiqueta visible (ej. "Registrar")
            $table->string('clave');                          // slug estable (ej. "registrar")
            $table->string('ruta')->nullable();               // nombre de ruta concreta (opcional)
            $table->string('descripcion')->nullable();
            $table->boolean('est')->default(true);
            $table->timestamps();

            $table->unique(['modulo_id', 'clave']);           // clave unica dentro de cada modulo
        });

        // MATRIZ DE ACCESO: que acciones tiene cada rol. Es el corazon del sistema.
        Schema::create('rol_accion', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('rol')->cascadeOnDelete();
            $table->foreignId('accion_id')->constrained('accion')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['rol_id', 'accion_id']);         // PK compuesta: un par no se repite
        });

        // Asignacion de rol a usuario CON VIGENCIA (el `permiso` del docente).
        // fini/ffin acotan en el tiempo cuando el rol esta activo para ese usuario.
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('rol')->cascadeOnDelete();
            $table->date('fini')->nullable();                 // inicio de vigencia
            $table->date('ffin')->nullable();                 // fin de vigencia (null = sin limite)
            $table->boolean('est')->default(true);
            $table->timestamps();

            $table->primary(['user_id', 'rol_id']);
        });
    }

    public function down(): void
    {
        // Orden inverso por las llaves foraneas.
        Schema::dropIfExists('usuario_rol');
        Schema::dropIfExists('rol_accion');
        Schema::dropIfExists('accion');
        Schema::dropIfExists('modulo');
        Schema::dropIfExists('rol');
    }
};
