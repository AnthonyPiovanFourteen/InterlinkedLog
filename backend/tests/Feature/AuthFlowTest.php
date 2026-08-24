<?php

namespace Tests\Feature;

class AuthFlowTest extends ApiTestCase
{
    public function test_login_with_valid_credentials_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@interlinked.io',
            'password' => 'admin123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role', 'company_id', 'status'],
            ])
            ->assertJsonPath('user.email', 'admin@interlinked.io')
            ->assertJsonPath('user.role', 'Admin')
            ->assertJsonPath('user.status', 'Ativo');
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@interlinked.io',
            'password' => 'senha-incorreta',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nao-existe@interlinked.io',
            'password' => 'admin123',
        ]);

        $response->assertStatus(401);
    }

    public function test_protected_endpoint_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/quotations')->assertStatus(401);
    }
}