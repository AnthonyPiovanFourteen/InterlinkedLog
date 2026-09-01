<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->index('status');
            $table->index(['company_id', 'status']);
        });

        Schema::table('carriers', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('freight_tables', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // O MySQL não permite dropar o índice composto enquanto a FK de
            // company_id depender dele — dropar a FK primeiro e recriá-la
            // depois (o índice automático de company_id volta junto).
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['status']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::table('carriers', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('freight_tables', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
