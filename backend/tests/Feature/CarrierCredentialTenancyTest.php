<?php

namespace Tests\Feature;

use App\Domain\Entities\CarrierCredential;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Models\Carrier;
use App\Models\CarrierCredential as CredentialModel;
use Illuminate\Support\Str;

class CarrierCredentialTenancyTest extends ApiTestCase
{
    private function repo(): CarrierCredentialRepository
    {
        return app(CarrierCredentialRepository::class);
    }

    private function store(string $companyId, string $carrierId, string $user): void
    {
        $this->repo()->save(new CarrierCredential(
            id: Str::orderedUuid()->toString(),
            companyId: $companyId, carrierId: $carrierId,
            gateway: 'braspress', secrets: ['username' => $user, 'password' => 'p'],
        ));
    }

    public function test_credential_is_isolated_per_tenant(): void
    {
        $carrierId = Carrier::first()->id;
        $tenantB = $this->createTenant('b@interlinked.io', 'Tenant B');

        $this->store($this->adminCompanyId, $carrierId, 'usuario-a');
        $this->store($tenantB['company_id'], $carrierId, 'usuario-b');

        // Mesma transportadora (catálogo global), credenciais distintas.
        $this->assertSame('usuario-a', $this->repo()->findForCarrier($this->adminCompanyId, $carrierId)?->secret('username'));
        $this->assertSame('usuario-b', $this->repo()->findForCarrier($tenantB['company_id'], $carrierId)?->secret('username'));
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $carrierId = Carrier::first()->id;
        $this->store($this->adminCompanyId, $carrierId, 'segredo-visivel');

        $raw = CredentialModel::withoutGlobalScopes()->first()->getRawOriginal('secrets');

        $this->assertStringNotContainsString('segredo-visivel', $raw);
        $this->assertSame('segredo-visivel', $this->repo()->findForCarrier($this->adminCompanyId, $carrierId)?->secret('username'));
    }

    public function test_active_for_company_returns_only_own_credentials(): void
    {
        $carrierId = Carrier::first()->id;
        $tenantB = $this->createTenant('c@interlinked.io', 'Tenant C');

        $this->store($tenantB['company_id'], $carrierId, 'usuario-b');

        $this->assertSame([], $this->repo()->activeForCompany($this->adminCompanyId));
        $this->assertCount(1, $this->repo()->activeForCompany($tenantB['company_id']));
    }
}
