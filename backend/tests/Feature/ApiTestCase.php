<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $adminCompanyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('request', \Illuminate\Http\Request::create('/'));
        $this->seed();

        $login = $this->postJson('/api/v1/login', [
            'email' => 'admin@interlinked.io',
            'password' => 'admin123',
        ]);
        $login->assertOk();

        $this->adminToken = $login->json('token');
        $this->adminCompanyId = $login->json('user.company_id');
    }

    protected function authHeaders(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->adminToken)];
    }

    protected function createTenant(string $email, string $name = 'Usuário Tenant'): array
    {
        $company = Company::create([
            'id' => Str::orderedUuid()->toString(),
            'name' => 'Empresa ' . $name,
            'cnpj' => '99.999.999/0001-99',
            'type' => 'Enterprise',
        ]);

        User::create([
            'id' => Str::orderedUuid()->toString(),
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('admin123'),
            'role' => 'Admin',
            'status' => 'Ativo',
        ]);

        $login = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'admin123',
        ]);
        $login->assertOk();

        return [
            'token' => $login->json('token'),
            'company_id' => $login->json('user.company_id'),
            'company' => $company,
        ];
    }

    protected function createQuotation(?string $token = null, array $overrides = []): array
    {
        $response = $this->postJson('/api/v1/quotations', array_merge([
            'nf_number' => '000100',
            'sender_cnpj' => '12.345.678/0001-99',
            'receiver_cnpj' => '98.765.432/0001-88',
            'origin_cep' => '01000-000',
            'destination_cep' => '86020-000',
            'weight' => 45,
            'boxes' => 10,
            'volume' => 0.15,
            'cargo_value' => 5000,
        ], $overrides), $this->authHeaders($token));

        $response->assertStatus(201);

        return $response->json('data');
    }

    protected function createCarrier(array $overrides = [], ?string $token = null): array
    {
        $response = $this->postJson('/api/v1/carriers', array_merge([
            'name' => 'Transportadora Teste',
            'cnpj' => '11.111.111/0001-11',
            'origin_city' => 'São Paulo',
            'origin_state' => 'SP',
        ], $overrides), $this->authHeaders($token));

        $response->assertStatus(201);

        return $response->json('data');
    }

    protected function createFreightTable(
        string $carrierId,
        array $overrides = [],
        ?string $token = null,
    ): array {
        $response = $this->postJson('/api/v1/freight-tables', array_merge([
            'name' => 'Tabela Teste',
            'carrier_id' => $carrierId,
            'origin_city' => 'São Paulo',
            'validity_start' => '2026-01-01',
            'validity_end' => '2026-12-31',
            'routes' => [['city' => 'Londrina', 'state' => 'PR', 'deadline' => 2]],
            'weight_ranges' => [['start' => 0, 'end' => 100, 'value' => 150]],
            'fees' => [],
        ], $overrides), $this->authHeaders($token));

        $response->assertStatus(201);

        $table = $response->json('data');

        $this->patchJson('/api/v1/freight-tables/' . $table['id'], [
            'status' => 'Ativa',
        ], $this->authHeaders($token))->assertOk();

        return $table;
    }
}