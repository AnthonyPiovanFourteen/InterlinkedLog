<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Concerns\TenantScoped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_phpat_hexagonal_rules_pass(): void
    {
        $process = new Process([
            'vendor/bin/phpstan',
            'analyse',
            '--configuration=phpstan.arch.neon',
            '--no-progress',
            '--error-format=raw',
            '--memory-limit=512M',
        ]);
        $process->setTimeout(180);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
    }

    public function test_domain_ports_are_interfaces(): void
    {
        foreach (glob(app_path('Domain/Repositories/*.php')) as $file) {
            $class = 'App\\Domain\\Repositories\\'.basename($file, '.php');
            $this->assertTrue(
                (new \ReflectionClass($class))->isInterface(),
                "{$class} deveria ser interface",
            );
        }

        foreach (glob(app_path('Domain/Services/*.php')) as $file) {
            $class = 'App\\Domain\\Services\\'.basename($file, '.php');
            $this->assertTrue(
                (new \ReflectionClass($class))->isInterface(),
                "{$class} deveria ser interface",
            );
        }
    }

    public function test_models_with_company_id_use_tenant_scope(): void
    {
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if ($class === Company::class) {
                continue;
            }
            $table = (new $class)->getTable();
            if (! Schema::hasColumn($table, 'company_id')) {
                continue;
            }
            $this->assertTrue(
                in_array(TenantScoped::class, class_uses_recursive($class), true),
                "{$class} tem company_id na tabela {$table} e deveria usar TenantScoped",
            );
        }
    }
}
