<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('carrier_name');
            $table->string('nf_number', 20);
            $table->string('origin_city');
            $table->string('destination_city');
            $table->string('destination_state', 2);
            $table->decimal('freight_value', 10, 2);
            $table->decimal('fees', 10, 2);
            $table->decimal('final_value', 10, 2);
            $table->integer('deadline');
            $table->string('status', 30);
            $table->string('document_number');
            $table->string('cte_number')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
