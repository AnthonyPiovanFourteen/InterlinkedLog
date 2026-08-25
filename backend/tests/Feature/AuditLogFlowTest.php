<?php

namespace Tests\Feature;

class AuditLogFlowTest extends ApiTestCase
{
    public function test_store_creates_audit_log_for_tenant(): void
    {
        $response = $this->postJson('/api/v1/audit-logs', [
            'module' => 'contratos',
            'action' => 'cancelar',
            'entity_type' => 'contract',
            'entity_id' => '00000000-0000-0000-0000-000000000001',
            'new_values' => '{"status":"Cancelado"}',
        ], $this->authHeaders());

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $response->json('data.id'),
            'company_id' => $this->adminCompanyId,
            'module' => 'contratos',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson('/api/v1/audit-logs', [
            'module' => '',
            'action' => 'cancelar',
            'entity_type' => 'contract',
        ], $this->authHeaders())->assertStatus(422);
    }

    public function test_index_lists_only_own_tenant_logs(): void
    {
        $this->postJson('/api/v1/audit-logs', [
            'module' => 'contratos',
            'action' => 'cancelar',
            'entity_type' => 'contract',
        ], $this->authHeaders())->assertStatus(201);

        $tenantB = $this->createTenant('b@interlinked.io');

        $logsA = $this->getJson('/api/v1/audit-logs', $this->authHeaders())
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $logsA);

        $logsB = $this->getJson('/api/v1/audit-logs', $this->authHeaders($tenantB['token']))
            ->assertOk()
            ->json('data');
        $this->assertSame([], $logsB);
    }
}