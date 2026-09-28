<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Contract;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Str;

class FreightBenchmarkTest extends ApiTestCase
{
    /** Contrato efetivado na mesma rota, com peso e valor dados. */
    private function pastContract(float $weight, float $value, int $deadline, int $monthsAgo = 1, string $status = 'Agendado'): void
    {
        $quotationId = Str::orderedUuid()->toString();

        Quotation::create([
            'id' => $quotationId,
            'company_id' => $this->adminCompanyId,
            'user_id' => User::where('email', 'admin@interlinked.io')->first()->id,
            'nf_number' => '9'.random_int(1000, 9999),
            'sender_cnpj' => '1', 'receiver_cnpj' => '2',
            'origin_cep' => '01000000', 'destination_cep' => '86020000',
            'origin_city' => 'São Paulo', 'destination_city' => 'Londrina', 'destination_state' => 'PR',
            'weight' => $weight, 'boxes' => 1, 'volume' => 0.15, 'cargo_value' => 5000,
            'status' => 'CONTRATADA', 'valid_until' => now()->addDays(7),
        ]);

        $contract = Contract::create([
            'id' => Str::orderedUuid()->toString(),
            'company_id' => $this->adminCompanyId,
            'quotation_id' => $quotationId,
            'carrier_id' => Carrier::first()->id,
            'carrier_name' => 'Transportadora',
            'nf_number' => '9'.random_int(1000, 9999),
            'origin_city' => 'São Paulo', 'destination_city' => 'Londrina', 'destination_state' => 'PR',
            'freight_value' => $value, 'fees' => 0, 'final_value' => $value,
            'deadline' => $deadline, 'status' => $status,
            'document_number' => 'SC-TESTE',
        ]);

        // created_at não está no fillable de Contract, então é descartado no
        // create() — a data precisa ser forçada depois.
        $contract->forceFill(['created_at' => now()->subMonths($monthsAgo)])->saveQuietly();
    }

    private function benchmark(): array
    {
        $quotation = $this->createQuotation();

        return $this->getJson("/api/v1/quotations/{$quotation['id']}", $this->authHeaders())
            ->json('data.benchmark');
    }

    public function test_without_history_the_benchmark_is_empty(): void
    {
        $benchmark = $this->benchmark();

        $this->assertSame(0, $benchmark['sample']);
        $this->assertNull($benchmark['median_value']);
        $this->assertSame(6, $benchmark['months']);
    }

    public function test_median_not_average_so_an_outlier_does_not_distort(): void
    {
        // A cotação de teste usa peso 45; todos dentro de ±30%.
        $this->pastContract(45, 100.00, 3);
        $this->pastContract(45, 120.00, 4);
        $this->pastContract(45, 5000.00, 9); // frete atípico

        $benchmark = $this->benchmark();

        $this->assertSame(3, $benchmark['sample']);
        // Mediana 120; a média seria 1740.
        $this->assertEquals(120, $benchmark['median_value']);
        $this->assertSame(4, $benchmark['median_deadline']);
    }

    public function test_weight_outside_tolerance_is_ignored(): void
    {
        $this->pastContract(45, 100.00, 3);
        $this->pastContract(200, 900.00, 3); // fora de ±30% de 45

        $benchmark = $this->benchmark();

        $this->assertSame(1, $benchmark['sample']);
        $this->assertEquals(100, $benchmark['median_value']);
    }

    public function test_older_than_the_window_is_ignored(): void
    {
        $this->pastContract(45, 100.00, 3, monthsAgo: 10);

        $this->assertSame(0, $this->benchmark()['sample']);
    }

    public function test_cancelled_contract_is_ignored(): void
    {
        $this->pastContract(45, 100.00, 3, status: 'Cancelado');

        $this->assertSame(0, $this->benchmark()['sample']);
    }
}
