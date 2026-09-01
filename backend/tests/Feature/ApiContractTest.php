<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;

class ApiContractTest extends ApiTestCase
{
    public function test_login_response_shape(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'admin@interlinked.io',
            'password' => 'admin123',
        ])->assertOk()->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role', 'company_id', 'status'],
        ]);
    }

    public function test_quotations_index_response_shape(): void
    {
        $this->getJson('/api/v1/quotations', $this->authHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'nf_number', 'destination_city', 'destination_state',
                    'weight', 'cargo_value', 'status', 'results_count',
                    'best_value', 'valid_until', 'created_at',
                ]],
            ]);
    }

    public function test_contracts_index_response_shape(): void
    {
        $this->getJson('/api/v1/contracts', $this->authHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'nf_number', 'carrier_name', 'final_value',
                    'status', 'document_number', 'cte_number',
                    'origin_city', 'destination_city', 'deadline', 'created_at',
                ]],
            ]);
    }

    public function test_tracking_index_response_shape(): void
    {
        $this->getJson('/api/v1/tracking', $this->authHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'contract_id', 'nf_number', 'carrier_name',
                    'origin_city', 'destination_city', 'status', 'deadline',
                    'events' => [['id', 'title', 'date', 'time', 'observation']],
                ]],
            ]);
    }

    public function test_every_api_route_requires_token(): void
    {
        // Allowlist explícita e nomeada: rotas legítimas sem autenticação.
        // Rota nova fora do grupo autenticado quebra o teste até alguém
        // declará-la conscientemente como pública.
        $public = [
            'POST api/v1/login',
            'GET up',
            'GET storage/{path}', // assets públicos (storage:link) — responde 403/404 sem token
        ];

        /** @var Router $router */
        $router = app(Router::class);
        $tested = 0;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $method = $route->methods()[0];

            if (in_array("{$method} {$route->uri()}", $public, true)) {
                continue;
            }

            $uri = preg_replace('/\{[^}]+\}/', '00000000-0000-0000-0000-000000000000', $route->uri());

            $response = $this->call($method, '/'.$uri);
            $this->assertSame(
                401,
                $response->getStatusCode(),
                "rota {$method} /{$uri} deveria exigir token",
            );
            $tested++;
        }

        $this->assertGreaterThanOrEqual(
            39,
            $tested,
            'o laço esvaziou — middleware ou estrutura do grupo mudou em silêncio',
        );
    }
}
