<?php

namespace Tests\Unit;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\FreightTable;
use App\Domain\Entities\Quotation;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\FreightTableRepository;
use App\Domain\Services\CepLookupService;
use App\Infrastructure\Services\QuotationEngine;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * frete_minimo é piso sobre o frete e cubagem é fator kg/m³ — nenhum dos dois
 * é taxa somável. Cada teste aqui falha no comportamento anterior, que somava
 * os dois como moeda.
 */
class PricingSemanticsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function engine(array $ranges, array $fees): QuotationEngine
    {
        $carrier = new Carrier(
            id: 'c1', name: 'Transportadora', cnpj: '00.000.000/0001-00',
            originCity: 'São Paulo', originState: 'SP', status: CarrierStatus::ATIVA,
        );

        $table = new FreightTable(
            id: 't1', name: 'Tabela', carrierId: 'c1', companyId: 'emp1',
            originCity: 'São Paulo', validityStart: '2026-01-01', validityEnd: '2026-12-31',
            status: 'Ativa',
            routes: [['city' => 'Londrina', 'state' => 'PR', 'deadline' => 3, 'weightRanges' => $ranges]],
            fees: $fees,
        );

        $carriers = Mockery::mock(CarrierRepository::class);
        $carriers->shouldReceive('findAll')->andReturn([$carrier]);

        $tables = Mockery::mock(FreightTableRepository::class);
        $tables->shouldReceive('findActiveByCarrierAndRoute')->andReturn($table);

        // O motor recebe o serviço de CEP, mas process() não o usa — a
        // resolução de CEP acontece antes, no controller.
        $cep = Mockery::mock(CepLookupService::class);

        return new QuotationEngine($carriers, $tables, $cep);
    }

    private function quotation(float $weight, float $volume, float $cargoValue = 1000): Quotation
    {
        return Quotation::create(
            id: 'q1', companyId: 'emp1', userId: 'u1',
            nfNumber: '1', senderCnpj: '1', receiverCnpj: '2',
            originCep: '01000-000', destinationCep: '86020-000',
            originCity: 'São Paulo', destinationCity: 'Londrina', destinationState: 'PR',
            weight: $weight, boxes: 1, volume: $volume, cargoValue: $cargoValue,
            validUntil: '2026-12-31',
        );
    }

    public function test_cubagem_is_a_factor_not_a_fee(): void
    {
        $ranges = [
            ['start' => 0, 'end' => 30, 'value' => 85.50, 'deadline' => 2],
            ['start' => 31, 'end' => 100, 'value' => 142.00, 'deadline' => 3],
        ];
        // Carga leve e volumosa: 10 kg reais, 0,2 m³ → cubado 60 kg.
        $results = $this->engine($ranges, [['type' => 'cubagem', 'value' => 300, 'percentage' => 0]])
            ->process($this->quotation(10, 0.2));

        // O peso cubado (60) manda, não o real (10): faixa [31,100] → 142,00.
        $this->assertSame(142.00, $results[0]['freight_value']);
        // E a cubagem não entra como taxa.
        $this->assertSame(0.0, $results[0]['fees']);
    }

    public function test_frete_minimo_is_a_floor_not_a_fee(): void
    {
        $ranges = [['start' => 0, 'end' => 30, 'value' => 20.00, 'deadline' => 2]];

        $results = $this->engine($ranges, [['type' => 'frete_minimo', 'value' => 50, 'percentage' => 0]])
            ->process($this->quotation(5, 0.01));

        // Frete de tabela (20,00) abaixo do piso (50,00) → cobra-se o piso.
        $this->assertSame(50.00, $results[0]['freight_value']);
        $this->assertSame(0.0, $results[0]['fees']);
    }

    public function test_frete_minimo_does_not_inflate_freight_above_the_floor(): void
    {
        $ranges = [['start' => 0, 'end' => 100, 'value' => 142.00, 'deadline' => 3]];

        $results = $this->engine($ranges, [['type' => 'frete_minimo', 'value' => 50, 'percentage' => 0]])
            ->process($this->quotation(45, 0.15));

        // Frete acima do piso permanece intacto...
        $this->assertSame(142.00, $results[0]['freight_value']);
        // ...e o piso não vira taxa. Antes, os 50,00 eram somados aqui.
        $this->assertSame(0.0, $results[0]['fees']);
        $this->assertSame(142.00, $results[0]['final_value']);
    }

    public function test_weight_between_ranges_resolves_to_the_next_tier(): void
    {
        $ranges = [
            ['start' => 0, 'end' => 30, 'value' => 85.50, 'deadline' => 2],
            ['start' => 31, 'end' => 100, 'value' => 142.00, 'deadline' => 3],
        ];
        // 0,1005 m³ × 300 = 30,15 kg — caía na lacuna entre [0,30] e [31,100].
        $results = $this->engine($ranges, [['type' => 'cubagem', 'value' => 300, 'percentage' => 0]])
            ->process($this->quotation(1, 0.1005));

        $this->assertSame(142.00, $results[0]['freight_value']);
        $this->assertSame(3, $results[0]['deadline']);
    }
}
