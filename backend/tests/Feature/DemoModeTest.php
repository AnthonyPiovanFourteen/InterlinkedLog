<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\CarrierCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sistema novo começa vazio. Dados fictícios só com DEMO_MODE ligado.
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_command_creates_nothing_when_demo_is_off(): void
    {
        config(['app.demo_mode' => false]);

        $this->artisan('app:seed')
            ->expectsOutputToContain('Modo demo desligado')
            ->assertSuccessful();

        $this->assertSame(0, Company::withoutGlobalScopes()->count());
        $this->assertSame(0, Carrier::count());
    }

    public function test_seed_command_populates_demo_data_when_on(): void
    {
        config(['app.demo_mode' => true]);

        $this->artisan('app:seed')->assertSuccessful();

        $this->assertGreaterThan(0, Company::withoutGlobalScopes()->count());
        $this->assertGreaterThan(0, Carrier::count());
    }

    public function test_carrier_catalog_is_seeded_regardless_of_demo_mode(): void
    {
        config(['app.demo_mode' => false]);

        $this->seed(CarrierCatalogSeeder::class);

        // As quatro com adaptador existem mesmo com o modo demo desligado —
        // sem elas não haveria onde configurar a credencial.
        $names = Carrier::pluck('name')->all();
        foreach (['Braspress', 'Jadlog', 'Jamef', 'Loggi', 'Rodonaves'] as $expected) {
            $this->assertContains($expected, $names);
        }

        // E nada de dado fictício junto.
        $this->assertSame(5, Carrier::count());
        $this->assertSame(0, Company::withoutGlobalScopes()->count());
    }

    public function test_carrier_catalog_is_idempotent(): void
    {
        $this->seed(CarrierCatalogSeeder::class);
        $this->seed(CarrierCatalogSeeder::class);

        $this->assertSame(5, Carrier::count());
    }

    public function test_demo_seed_does_not_duplicate_the_catalog(): void
    {
        config(['app.demo_mode' => true]);

        $this->seed(CarrierCatalogSeeder::class);
        $this->artisan('app:seed')->assertSuccessful();

        // Braspress e Rodonaves estão nas duas listas e não podem duplicar.
        $this->assertSame(1, Carrier::where('name', 'Braspress')->count());
        $this->assertSame(1, Carrier::where('name', 'Rodonaves')->count());
    }

    public function test_create_admin_bootstraps_without_demo_data(): void
    {
        $this->artisan('app:create-admin', [
            '--company' => 'Transportes Reais Ltda',
            '--email' => 'chefe@empresa.com.br',
            '--password' => 'senha-forte',
        ])->assertSuccessful();

        $this->assertSame(1, Company::withoutGlobalScopes()->count());
        $this->assertSame(1, User::withoutGlobalScopes()->count());
        // Nenhuma transportadora nem tabela fictícia.
        $this->assertSame(0, Carrier::count());

        $this->postJson('/api/v1/login', [
            'email' => 'chefe@empresa.com.br',
            'password' => 'senha-forte',
        ])->assertOk();
    }

    public function test_create_admin_requires_credentials(): void
    {
        $this->artisan('app:create-admin')->assertFailed();

        $this->assertSame(0, Company::withoutGlobalScopes()->count());
    }

    public function test_create_admin_is_idempotent(): void
    {
        $args = ['--company' => 'Empresa', '--email' => 'a@b.com', '--password' => 'x'];

        $this->artisan('app:create-admin', $args)->assertSuccessful();
        $this->artisan('app:create-admin', $args)
            ->expectsOutputToContain('Já existe usuário')
            ->assertSuccessful();

        $this->assertSame(1, User::withoutGlobalScopes()->count());
    }
}
