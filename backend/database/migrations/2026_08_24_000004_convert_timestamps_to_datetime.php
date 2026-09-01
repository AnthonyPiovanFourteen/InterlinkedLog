<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $timestampTables = [
        'companies',
        'users',
        'carriers',
        'freight_tables',
        'freight_table_routes',
        'freight_table_weight_ranges',
        'freight_table_fees',
        'subscriptions',
        'quotations',
        'quotation_results',
        'contracts',
        'tracking_events',
    ];

    public function up(): void
    {
        foreach ($this->timestampTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->datetime('created_at')->nullable()->change();
                $t->datetime('updated_at')->nullable()->change();
            });
        }

        foreach (['audit_logs', 'system_logs'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->datetime('created_at')->nullable()->change();
            });
        }

        Schema::table('users', function (Blueprint $t) {
            $t->datetime('last_access_at')->nullable()->change();
        });

        Schema::table('contracts', function (Blueprint $t) {
            $t->datetime('cancelled_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach ($this->timestampTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('created_at')->nullable()->change();
                $t->timestamp('updated_at')->nullable()->change();
            });
        }

        foreach (['audit_logs', 'system_logs'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('created_at')->nullable()->change();
            });
        }

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('last_access_at')->nullable()->change();
        });

        Schema::table('contracts', function (Blueprint $t) {
            $t->timestamp('cancelled_at')->nullable()->change();
        });
    }
};
