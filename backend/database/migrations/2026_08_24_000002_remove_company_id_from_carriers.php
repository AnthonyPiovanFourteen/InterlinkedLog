<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->string('status', 20)->default('Ativa')->change();
        });
    }

    public function down(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->foreignUuid('company_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }
};
