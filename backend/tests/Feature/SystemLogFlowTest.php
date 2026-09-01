<?php

namespace Tests\Feature;

class SystemLogFlowTest extends ApiTestCase
{
    public function test_store_creates_system_log_for_tenant(): void
    {
        $response = $this->postJson('/api/v1/system-logs', [
            'level' => 'INFO',
            'event' => 'cotacao',
            'message' => 'Cotação criada',
        ], $this->authHeaders());

        $response->assertStatus(201);

        $this->assertDatabaseHas('system_logs', [
            'id' => $response->json('data.id'),
            'company_id' => $this->adminCompanyId,
            'level' => 'INFO',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson('/api/v1/system-logs', [
            'level' => 'DEBUG',
            'event' => 'cotacao',
            'message' => 'Inválido',
        ], $this->authHeaders())->assertStatus(422);
    }

    public function test_index_lists_only_own_tenant_logs(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');

        $logsA = $this->getJson('/api/v1/system-logs', $this->authHeaders())
            ->assertOk()
            ->json('data');
        $this->assertCount(10, $logsA);

        $logsB = $this->getJson('/api/v1/system-logs', $this->authHeaders($tenantB['token']))
            ->assertOk()
            ->json('data');
        $this->assertSame([], $logsB);
    }
}
