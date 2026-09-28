<?php

namespace Tests\Feature;

use App\Domain\Repositories\CarrierCredentialRepository;
use App\Models\Carrier;
use App\Models\CarrierCredential as CredentialModel;

class CarrierCredentialApiTest extends ApiTestCase
{
    private function carrierId(): string
    {
        return Carrier::first()->id;
    }

    private function braspress(): array
    {
        return ['username' => 'u', 'password' => 'p'];
    }

    private function create(array $overrides = [], ?string $token = null)
    {
        return $this->postJson('/api/v1/carrier-credentials', array_merge([
            'carrier_id' => $this->carrierId(),
            'gateway' => 'braspress',
            'secrets' => $this->braspress(),
        ], $overrides), $this->authHeaders($token));
    }

    public function test_gateways_endpoint_declares_required_secrets(): void
    {
        $response = $this->getJson('/api/v1/carrier-credentials/gateways', $this->authHeaders());

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('required_secrets', 'name');

        $this->assertSame(['username', 'password'], $names['braspress']);
        $this->assertSame(['client_id', 'client_secret', 'company_id'], $names['loggi']);
        $this->assertSame(['token', 'modalidade', 'cubage_factor'], $names['jadlog']);
    }

    public function test_gateways_endpoint_explains_each_secret(): void
    {
        $gateways = collect($this->getJson('/api/v1/carrier-credentials/gateways', $this->authHeaders())
            ->json('data'));

        foreach ($gateways as $gateway) {
            foreach ($gateway['required_secrets'] as $key) {
                // Todo segredo exigido precisa de explicação: é o que a tela
                // mostra no ícone de informação.
                $this->assertArrayHasKey($key, $gateway['secret_hints'], "{$gateway['name']}.{$key}");
                $this->assertNotSame('', trim($gateway['secret_hints'][$key]));
            }
        }

        // Uma amostra do conteúdo, para a explicação não virar placeholder.
        $jamef = $gateways->firstWhere('name', 'jamef');
        $this->assertStringContainsString('CNPJ', $jamef['secret_hints']['documento_devedor']);

        // E todo gateway aponta para a documentação oficial.
        foreach ($gateways as $gateway) {
            $this->assertNotNull($gateway['documentation_url'], $gateway['name']);
            $this->assertStringStartsWith('https://', $gateway['documentation_url']);
        }
    }

    public function test_store_creates_credential_without_ever_returning_secrets(): void
    {
        $response = $this->create();

        $response->assertStatus(201);
        // Só as CHAVES configuradas voltam, nunca os valores.
        $this->assertSame(['username', 'password'], $response->json('data.configured_secrets'));
        $this->assertStringNotContainsString('"u"', $response->getContent());
        $this->assertArrayNotHasKey('secrets', $response->json('data'));
    }

    public function test_index_never_returns_secrets(): void
    {
        $this->create();

        $response = $this->getJson('/api/v1/carrier-credentials', $this->authHeaders());

        $response->assertOk();
        $this->assertArrayNotHasKey('secrets', $response->json('data.0'));
        $this->assertSame(['username', 'password'], $response->json('data.0.configured_secrets'));
    }

    public function test_missing_required_secret_is_rejected(): void
    {
        $response = $this->create(['secrets' => ['username' => 'u']]);

        $response->assertStatus(422);
        $this->assertSame(['password'], $response->json('errors.secrets'));
    }

    public function test_empty_required_secret_is_rejected(): void
    {
        $response = $this->create(['secrets' => ['username' => 'u', 'password' => '   ']]);

        $response->assertStatus(422);
        $this->assertSame(['password'], $response->json('errors.secrets'));
    }

    public function test_unknown_gateway_is_rejected(): void
    {
        $this->create(['gateway' => 'inexistente'])->assertStatus(422);
    }

    public function test_duplicate_credential_for_same_carrier_is_rejected(): void
    {
        $this->create()->assertStatus(201);
        $this->create()->assertStatus(422);
    }

    public function test_unknown_carrier_returns_404(): void
    {
        $this->create(['carrier_id' => '00000000-0000-0000-0000-000000000000'])->assertStatus(404);
    }

    public function test_non_admin_cannot_write(): void
    {
        $user = $this->createTenantUser('op@interlinked.io', 'Usuário');

        $this->create([], $user['token'])->assertStatus(403);
    }

    public function test_update_replaces_secrets_entirely(): void
    {
        $id = $this->create()->json('data.id');

        $response = $this->patchJson("/api/v1/carrier-credentials/{$id}", [
            'secrets' => ['username' => 'novo', 'password' => 'novo'],
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertSame('novo', app(CarrierCredentialRepository::class)
            ->findById($this->adminCompanyId, $id)?->secret('username'));
    }

    public function test_update_can_deactivate_without_resending_secrets(): void
    {
        $id = $this->create()->json('data.id');

        $this->patchJson("/api/v1/carrier-credentials/{$id}", ['active' => false], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.active', false);

        // Segredos preservados.
        $this->assertSame('u', app(CarrierCredentialRepository::class)
            ->findById($this->adminCompanyId, $id)?->secret('username'));
    }

    public function test_update_with_incomplete_secrets_is_rejected(): void
    {
        $id = $this->create()->json('data.id');

        $this->patchJson("/api/v1/carrier-credentials/{$id}", [
            'secrets' => ['username' => 'so-usuario'],
        ], $this->authHeaders())->assertStatus(422);
    }

    public function test_credential_of_another_tenant_is_invisible(): void
    {
        $id = $this->create()->json('data.id');
        $other = $this->createTenant('outro@interlinked.io', 'Outro');

        $this->patchJson("/api/v1/carrier-credentials/{$id}", ['active' => false], $this->authHeaders($other['token']))
            ->assertStatus(404);
        $this->deleteJson("/api/v1/carrier-credentials/{$id}", [], $this->authHeaders($other['token']))
            ->assertStatus(404);
        $this->getJson('/api/v1/carrier-credentials', $this->authHeaders($other['token']))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_destroy_removes_credential(): void
    {
        $id = $this->create()->json('data.id');

        $this->deleteJson("/api/v1/carrier-credentials/{$id}", [], $this->authHeaders())->assertOk();

        $this->assertSame(0, CredentialModel::withoutGlobalScopes()->count());
    }

    public function test_carriers_listing_says_which_have_integration_available(): void
    {
        $carriers = collect($this->getJson('/api/v1/carriers', $this->authHeaders())->json('data'));

        // Com adaptador de API.
        $this->assertSame('braspress', $carriers->firstWhere('name', 'Braspress')['gateway']);
        $this->assertSame('rodonaves', $carriers->firstWhere('name', 'Rodonaves')['gateway']);
        $this->assertSame('jamef', $carriers->firstWhere('name', 'Jamef')['gateway']);
        // Sem adaptador: segue disponível só por tabela.
        $this->assertNull($carriers->firstWhere('name', 'TNT Mercúrio')['gateway']);

        // Nenhuma integrada ainda.
        $this->assertSame(0, $carriers->where('integrated', true)->count());
    }

    public function test_integration_is_per_carrier_not_global(): void
    {
        $rodonaves = collect($this->getJson('/api/v1/carriers', $this->authHeaders())->json('data'))
            ->firstWhere('name', 'Rodonaves');

        $this->postJson('/api/v1/carrier-credentials', [
            'carrier_id' => $rodonaves['id'],
            'gateway' => 'rodonaves',
            'secrets' => ['username' => 'u', 'password' => 'p'],
        ], $this->authHeaders())->assertStatus(201);

        $carriers = collect($this->getJson('/api/v1/carriers', $this->authHeaders())->json('data'));

        // Só a Rodonaves fica integrada; Braspress segue disponível e desligada.
        $this->assertTrue($carriers->firstWhere('name', 'Rodonaves')['integrated']);
        $this->assertFalse($carriers->firstWhere('name', 'Braspress')['integrated']);
        $this->assertSame('braspress', $carriers->firstWhere('name', 'Braspress')['gateway']);
    }
}
