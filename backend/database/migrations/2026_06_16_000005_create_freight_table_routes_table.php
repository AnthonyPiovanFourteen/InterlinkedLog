<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_table_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freight_table_id')->constrained('freight_tables')->cascadeOnDelete();
            $table->string('origin_city');
            $table->string('origin_uf', 2);
            $table->string('destination_city');
            $table->string('destination_uf', 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_table_routes');
    }
};
