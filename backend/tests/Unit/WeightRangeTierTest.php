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
 * As faixas de peso da tabela são degraus contínuos. Comparar contra 'start'
 * criava lacunas: peso 30,5 não casava em [0,30] nem em [31,100] e o frete
 * voltava zero, sem erro.
 */
class WeightRangeTierTest extends TestCase
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

    public function test_weight_between_declared_ranges_resolves_to_the_next_tier(): void
    {
        $ranges = [
            ['start' => 0, 'end' => 30, 'value' => 85.50, 'deadline' => 2],
            ['start' => 31, 'end' => 100, 'value' => 142.00, 'deadline' => 3],
        ];

        // 30,5 kg: acima do teto da 1ª faixa, abaixo do piso declarado da 2ª.
        $results = $this->engine($ranges, [])->process($this->quotation(30.5, 0.01));

        $this->assertSame(142.00, $results[0]['freight_value']);
        $this->assertSame(3, $results[0]['deadline']);
    }
}
