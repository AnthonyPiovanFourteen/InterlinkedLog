<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Definido na contratação e imutável: automático quando a
            // transportadora tem API e credencial ativa, manual caso contrário.
            // Os dois não se misturam.
            $table->string('tracking_mode', 20)->default('manual')->after('status');
            $table->string('tracking_gateway', 50)->nullable()->after('tracking_mode');
        });

        Schema::table('tracking_events', function (Blueprint $table) {
            $table->string('origin', 20)->default('manual')->after('contract_id');
            // Identificador do evento na transportadora: evita inserir duas
            // vezes o mesmo evento a cada sincronismo.
            $table->string('external_id')->nullable()->after('origin');

            $table->unique(['contract_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            // O MySQL adota este índice único para a FK de contract_id e recusa
            // dropá-lo enquanto ela existir — mesmo padrão das migrations da
            // Fase 2: solta a FK, dropa o índice, recria a FK.
            $table->dropForeign(['contract_id']);
            $table->dropUnique(['contract_id', 'external_id']);
        });

        Schema::table('tracking_events', function (Blueprint $table) {
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->dropColumn(['origin', 'external_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['tracking_mode', 'tracking_gateway']);
        });
    }
};
