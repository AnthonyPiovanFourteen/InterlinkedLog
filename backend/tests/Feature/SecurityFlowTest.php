<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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