<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_results', function (Blueprint $table) {
            // De onde veio o número: tabela carregada pelo tenant ou API da
            // transportadora. Sem isso não há como auditar um contrato depois.
            $table->string('source', 20)->default('tabela')->after('carrier_name');
            $table->string('gateway', 50)->nullable()->after('source');
            $table->string('service')->nullable()->after('gateway');
            $table->string('protocol')->nullable()->after('service');

            $table->index(['quotation_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('quotation_results', function (Blueprint $table) {
            // O MySQL adota este índice composto para a FK de quotation_id e
            // recusa dropá-lo enquanto ela existir: solta a FK, dropa o índice
            // e recria a FK (o índice automático dela volta junto).
            $table->dropForeign(['quotation_id']);
            $table->dropIndex(['quotation_id', 'source']);
        });

        Schema::table('quotation_results', function (Blueprint $table) {
            $table->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
            $table->dropColumn(['source', 'gateway', 'service', 'protocol']);
        });
    }
};
