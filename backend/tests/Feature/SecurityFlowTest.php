<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_is_removed(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Usuário Novo',
            'email' => 'novo@teste.com',
            'password' => 'senha123',
            'company_name' => 'Empresa Nova',
        ])->assertStatus(404);
    }

    public function test_client_ip_is_not_derived_from_forwarded_header(): void
    {
        $request = Request::create('/probe', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $this->assertSame('10.0.0.5', $request->ip());
        $this->assertNotSame('203.0.113.9', $request->ip());
    }

    public function test_login_throttle_is_per_client_behind_proxy(): void
    {
        $this->seed();
        Cache::flush();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->postJson('/api/v1/login', [
                    'email' => 'admin@interlinked.io',
                    'password' => 'senha-errada',
                ])
                ->assertStatus(401);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/v1/login', [
                'email' => 'admin@interlinked.io',
                'password' => 'senha-errada',
            ])
            ->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/v1/login', [
                'email' => 'admin@interlinked.io',
                'password' => 'senha-errada',
            ])
            ->assertStatus(401);

        Cache::flush();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->seed();
        Cache::flush();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'admin@interlinked.io',
                'password' => 'senha-errada',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/login', [
            'email' => 'admin@interlinked.io',
            'password' => 'senha-errada',
        ])->assertStatus(429);

        Cache::flush();
    }
}