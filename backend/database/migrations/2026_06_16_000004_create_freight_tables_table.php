<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 20)->default('Ativa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_tables');
    }
};
