<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega datos fiscales de contacto al usuario: `ci` (documento de identidad) y `telefono`.
 * Nullable/opcionales. Los usa el pago por QR de PagoFacil (requisito #10) cuando
 * PAGOFACIL_USE_ENV_CLIENT=false, para enviar los datos reales del comprador en el QR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ci')->nullable()->after('email');
            $table->string('telefono')->nullable()->after('ci');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ci', 'telefono']);
        });
    }
};
