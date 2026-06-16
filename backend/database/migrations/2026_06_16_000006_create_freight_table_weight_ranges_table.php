<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_table_weight_ranges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freight_table_route_id')->constrained('freight_table_routes')->cascadeOnDelete();
            $table->decimal('min_weight', 8, 2);
            $table->decimal('max_weight', 8, 2);
            $table->decimal('freight_value', 10, 2);
            $table->integer('deadline_days');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_table_weight_ranges');
    }
};
