<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Mesma decisão de freight_tables: credencial é do tenant, não do
            // catálogo global de transportadoras.
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50);
            // Criptografado pelo cast do model (APP_KEY). A Fase B garante que
            // produção exige chave própria.
            $table->text('secrets');
            $table->boolean('active')->default(true);
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();

            $table->unique(['company_id', 'carrier_id']);
            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_credentials');
    }
};
