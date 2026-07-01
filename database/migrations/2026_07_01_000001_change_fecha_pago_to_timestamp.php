<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pago.fecha_pago` pasa de DATE a TIMESTAMP para guardar tambien la HORA del pago (util sobre todo
 * para el pago por QR, donde interesa el momento exacto). Los filtros por fecha (whereDate) siguen
 * funcionando; solo se gana la parte horaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->timestamp('fecha_pago')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable()->change();
        });
    }
};
