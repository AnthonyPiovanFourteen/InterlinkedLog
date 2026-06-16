<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_table_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freight_table_id')->constrained('freight_tables')->cascadeOnDelete();
            $table->string('fee_type', 50);
            $table->decimal('value', 10, 2);
            $table->boolean('is_percentage')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_table_fees');
    }
};
