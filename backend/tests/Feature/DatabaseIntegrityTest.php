<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Quotation;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_are_fully_reversible(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        $total = DB::table('migrations')->count();
        $this->assertGreaterThan(0, $total);

        Artisan::call('migrate:rollback', ['--step' => $total, '--force' => true]);
        $this->assertSame(0, DB::table('migrations')->count());

        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame($total, DB::table('migrations')->count());
    }

    public function test_seed_does_not_duplicate_companies_on_second_run(): void
    {
        Artisan::call('db:seed', ['--force' => true]);
        $this->assertSame(1, Company::count());

        Artisan::call('db:seed', ['--force' => true]);
        $this->assertSame(1, Company::count());
        $this->assertSame(1, User::where('email', 'admin@interlinked.io')->count());
    }

    public function test_deleting_company_cascades_to_dependents(): void
    {
        $this->seed();

        $companyId = Company::first()->id;
        $this->assertGreaterThan(0, User::where('company_id', $companyId)->count());
        $this->assertGreaterThan(0, Quotation::where('company_id', $companyId)->count());
        $this->assertGreaterThan(0, Contract::where('company_id', $companyId)->count());
        $this->assertGreaterThan(0, SystemLog::where('company_id', $companyId)->count());

        Company::destroy($companyId);

        $this->assertSame(0, User::where('company_id', $companyId)->count());
        $this->assertSame(0, Quotation::where('company_id', $companyId)->count());
        $this->assertSame(0, Contract::where('company_id', $companyId)->count());
        $this->assertSame(0, SystemLog::where('company_id', $companyId)->count());
    }

    public function test_fase2_indexes_exist_in_schema(): void
    {
        $expected = [
            'contracts' => ['contracts_status_index', 'contracts_company_id_status_index'],
            'carriers' => ['carriers_status_index'],
            'freight_tables' => ['freight_tables_status_index'],
            'quotations' => ['quotations_status_index'],
        ];

        foreach ($expected as $table => $indexes) {
            $present = collect(DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name')->all();
            foreach ($indexes as $index) {
                $this->assertContains($index, $present, "Índice {$index} ausente em {$table}");
            }
        }
    }

    public function test_tenant_scope_models_have_company_column_in_schema(): void
    {
        foreach ([
            'users', 'freight_tables', 'contracts', 'quotations', 'audit_logs', 'system_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'company_id'), "{$table} deveria ter company_id");
        }
    }
}