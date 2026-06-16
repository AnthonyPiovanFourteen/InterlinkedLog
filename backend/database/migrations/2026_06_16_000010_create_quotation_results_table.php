<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('carrier_name');
            $table->decimal('freight_value', 10, 2);
            $table->decimal('fees', 10, 2);
            $table->decimal('final_value', 10, 2);
            $table->integer('deadline');
            $table->json('fees_breakdown')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_results');
    }
};
