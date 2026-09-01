<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_tables', function (Blueprint $table) {
            $table->foreignUuid('company_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('freight_tables')
            ->whereNull('company_id')
            ->update(['company_id' => 'a208bd48-eaea-4bd2-9036-2188d194b392']);

        Schema::table('freight_tables', function (Blueprint $table) {
            $table->foreignUuid('company_id')->nullable(false)->change();
            $table->unique(['carrier_id', 'company_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::table('freight_tables', function (Blueprint $table) {
            // O MySQL não permite dropar o índice único enquanto uma FK
            // depender dele (carrier_id é o prefixo) — dropar as FKs
            // primeiro e recriar a de carrier_id ao final.
            $table->dropForeign(['carrier_id']);
            $table->dropForeign(['company_id']);
            $table->dropUnique(['carrier_id', 'company_id', 'valid_from']);
            $table->dropColumn('company_id');
        });

        Schema::table('freight_tables', function (Blueprint $table) {
            $table->foreign('carrier_id')->references('id')->on('carriers')->cascadeOnDelete();
        });
    }
};
