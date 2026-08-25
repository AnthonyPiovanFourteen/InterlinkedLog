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
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['status']);
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