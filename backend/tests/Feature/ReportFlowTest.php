<?php

namespace Tests\Feature;

class ReportFlowTest extends ApiTestCase
{
    public function test_dashboard_returns_expected_metrics_for_tenant(): void
    {
        $dashboard = $this->getJson('/api/v1/reports/dashboard', $this->authHeaders())
            ->assertOk()
            ->json('data');

        $this->assertSame(3, $dashboard['quotations_count']);
        $this->assertSame(1, $dashboard['contracts_count']);
        $this->assertSame(126, $dashboard['total_savings']);
        $this->assertSame(112.2, $dashboard['total_contracted']);
        $this->assertSame(33.33, $dashboard['conversion_rate']);
        $this->assertSame('Braspress', $dashboard['top_carrier']);
        $this->assertSame(1, $dashboard['quotations_by_status']['CONTRATADA']);
        $this->assertSame(2, $dashboard['quotations_by_status']['VALIDA']);
    }

    public function test_dashboard_of_tenant_without_data_returns_zeros(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');

        $dashboard = $this->getJson('/api/v1/reports/dashboard', $this->authHeaders($tenantB['token']))
            ->assertOk()
            ->json('data');

        $this->assertSame(0, $dashboard['quotations_count']);
        $this->assertSame(0, $dashboard['contracts_count']);
        $this->assertSame(0, $dashboard['total_contracted']);
        $this->assertSame([], $dashboard['quotations_by_status']);
    }

    public function test_detailed_report_lists_tenant_contracts(): void
    {
        $detailed = $this->getJson('/api/v1/reports/detailed', $this->authHeaders())
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $detailed['contracts_count']);
        $this->assertSame(112.2, $detailed['total_value']);
        $this->assertSame('Braspress', $detailed['by_carrier'][0]['carrier']);
        $this->assertSame('Marília → Londrina', $detailed['top_routes'][0]['route']);
        $this->assertSame(1, $detailed['top_routes'][0]['count']);
    }

    public function test_carrier_performance_uses_tenant_contracts_only(): void
    {
        $carrier = $this->getJson('/api/v1/carriers', $this->authHeaders())
            ->assertOk()
            ->json('data')[0];
        $performance = $this->getJson(
            '/api/v1/carriers/'.$carrier['id'].'/performance',
            $this->authHeaders(),
        )->assertOk()->json('data');

        $this->assertSame($carrier['id'], $performance['carrier_id']);
        $this->assertSame(1, $performance['total_contracts']);
        $this->assertSame(100, $performance['on_time_rate']);
        $this->assertSame(0, $performance['late_count']);
    }

    public function test_carrier_performance_of_other_tenant_does_not_leak(): void
    {
        $tenantB = $this->createTenant('b@interlinked.io');
        $carrier = $this->getJson('/api/v1/carriers', $this->authHeaders())
            ->assertOk()
            ->json('data')[0];
        $performance = $this->getJson(
            '/api/v1/carriers/'.$carrier['id'].'/performance',
            $this->authHeaders($tenantB['token']),
        )->assertOk()->json('data');

        $this->assertSame(0, $performance['total_contracts']);
    }
}
