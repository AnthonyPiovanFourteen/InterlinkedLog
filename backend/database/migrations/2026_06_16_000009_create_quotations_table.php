<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('nf_number', 20);
            $table->string('sender_cnpj', 18);
            $table->string('receiver_cnpj', 18);
            $table->string('origin_cep', 10);
            $table->string('destination_cep', 10);
            $table->string('origin_city');
            $table->string('destination_city');
            $table->string('destination_state', 2);
            $table->decimal('weight', 8, 2);
            $table->integer('boxes');
            $table->decimal('volume', 8, 3);
            $table->decimal('cargo_value', 10, 2);
            $table->string('status', 20)->default('VALIDA');
            $table->date('valid_until');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
