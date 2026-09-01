<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('carriers')
            ->where('status', 'Ativo')
            ->update(['status' => 'Ativa']);
    }

    public function down(): void
    {
        DB::table('carriers')
            ->where('status', 'Ativa')
            ->update(['status' => 'Ativo']);
    }
};
