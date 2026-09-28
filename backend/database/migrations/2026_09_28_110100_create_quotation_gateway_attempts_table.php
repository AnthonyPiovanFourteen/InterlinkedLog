<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_gateway_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50);
            // pendente → cotada | nao_atende | indisponivel | erro
            $table->string('status', 20)->default('pendente');
            // Mensagem para o usuário quando a transportadora falha; nula no
            // caminho feliz.
            $table->string('message')->nullable();
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();

            $table->unique(['quotation_id', 'gateway']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_gateway_attempts');
    }
};
