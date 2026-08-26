<?php

namespace Tests\Unit;

use App\Domain\Entities\Carrier as CarrierEntity;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\FreightTable as FreightTableEntity;
use App\Domain\Entities\Quotation as QuotationEntity;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\FreightTableRepository;
use App\Domain\Services\CepLookupService;
use App\Infrastructure\Services\QuotationEngine;
use Mockery;
use PHPUnit\Framework\TestCase;

class QuotationEngineTest extends TestCase
{
    private const COMPANY = 'company-1';

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function engine(
        array $carriers,
        callable|FreightTableEntity|null $tableResolver = null,
    ): QuotationEngine {
        $carrierRepo = Mockery::mock(CarrierRepository::class);
        $carrierRepo->shouldReceive('findAll')->andReturn($carriers);

        $freightRepo = Mockery::mock(FreightTableRepository::class);
        if (is_callable($tableResolver)) {
            $freightRepo->shouldReceive('findActiveByCarrierAndRoute')->andReturnUsing($tableResolver);
        } else {
            $freightRepo->shouldReceive('findActiveByCarrierAndRoute')->andReturn($tableResolver);
        }

        $cep = Mockery::mock(CepLookupService::class);

        return new QuotationEngine($carrierRepo, $freightRepo, $cep);
    }

    private function carrier(string $id, string $name, string $status = CarrierStatus::ATIVA): CarrierEntity
    {
        return new CarrierEntity(
            id: $id,
            name: $name,
            cnpj: '11.111.111/0001-11',
            originCity: 'São Paulo',
            originState: 'SP',
            status: $status,
        );
    }

    private function table(
        array $routes,
        array $fees = [],
        string $carrierId = 'carrier-1',
    ): FreightTableEntity {
        return new FreightTableEntity(
            id: 'table-' . $carrierId,
            companyId: self::COMPANY,
            name: 'Tabela',
            carrierId: $carrierId,
            originCity: 'São Paulo',
            validityStart: '2026-01-01',
            validityEnd: '2026-12-31',
            status: 'Ativa',
            routes: $routes,
            fees: $fees,
        );
    }

    private function route(
        string $city = 'Londrina',
        int $deadline = 3,
        array $ranges = [['start' => 0, 'end' => 100, 'value' => 100, 'deadline' => 3]],
    ): array {
        return [
            'city' => $city,
            'state' => 'PR',
            'deadline' => $deadline,
            'weightRanges' => $ranges,
        ];
    }

    private function quotation(string $destinationCity = 'Londrina', float $cargoValue = 1000): QuotationEntity
    {
        return QuotationEntity::create(
            id: 'quote-1',
            companyId: self::COMPANY,
            userId: 'user-1',
            nfNumber: '000100',
            senderCnpj: '12.345.678/0001-99',
            receiverCnpj: '98.765.432/0001-88',
            originCep: '01000-000',
            destinationCep: '86020-000',
            originCity: 'São Paulo',
            destinationCity: $destinationCity,
            destinationState: 'PR',
            weight: 45,
            boxes: 10,
            volume: 0.15,
            cargoValue: $cargoValue,
            validUntil: now()->addDays(7)->format('Y-m-d'),
        );
    }

    public function test_fixed_fee_is_added_flat(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Braspress')],
            $this->table([$this->route()], [['type' => 'despacho', 'value' => 25, 'percentage' => 0]]),
        );

        $results = $engine->process($this->quotation());

        $this->assertSame(125.0, $results[0]['final_value']);
        $this->assertSame(25.0, $results[0]['fees']);
    }

    public function test_percentage_fee_is_cargo_value_times_rate(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Braspress')],
            $this->table([$this->route()], [['type' => 'ad_valorem', 'value' => 10, 'percentage' => 10]]),
        );

        $results = $engine->process($this->quotation(cargoValue: 1000));

        $this->assertSame(100.0, $results[0]['fees']);
        $this->assertSame(200.0, $results[0]['final_value']);
    }

    public function test_mixed_fees_sum_fixed_and_percentage(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Braspress')],
            $this->table([$this->route()], [
                ['type' => 'despacho', 'value' => 25, 'percentage' => 0],
                ['type' => 'ad_valorem', 'value' => 10, 'percentage' => 10],
            ]),
        );

        $results = $engine->process($this->quotation(cargoValue: 1000));

        $this->assertSame(125.0, $results[0]['fees']);
    }

    public function test_breakdown_amounts_match_total_fees(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Braspress')],
            $this->table([$this->route()], [
                ['type' => 'despacho', 'value' => 25, 'percentage' => 0],
                ['type' => 'ad_valorem', 'value' => 10, 'percentage' => 10],
                ['type' => 'pedagio', 'value' => 5, 'percentage' => 5],
            ]),
        );

        $results = $engine->process($this->quotation(cargoValue: 1000));

        $this->assertSame(175.0, $results[0]['fees']);
        $this->assertSame(175.0, array_sum(array_column($results[0]['fees_breakdown'], 'amount')));
        $this->assertSame(100.0, $results[0]['fees_breakdown'][1]['amount']);
        $this->assertSame(50.0, $results[0]['fees_breakdown'][2]['amount']);
    }

    public function test_weight_range_of_matched_route_drives_freight_and_deadline(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Braspress')],
            $this->table([$this->route('Curitiba', 1, [
                ['start' => 0, 'end' => 30, 'value' => 300, 'deadline' => 1],
                ['start' => 31, 'end' => 100, 'value' => 600, 'deadline' => 9],
            ])]),
        );

        $results = $engine->process($this->quotation('Curitiba'));

        $this->assertSame(600.0, $results[0]['freight_value']);
        $this->assertSame(9, $results[0]['deadline']);
    }

    public function test_weight_outside_all_ranges_yields_zero_freight(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Braspress')],
            $this->table([$this->route('Londrina', 2, [
                ['start' => 0, 'end' => 30, 'value' => 100, 'deadline' => 2],
            ])]),
        );

        $results = $engine->process($this->quotation());

        $this->assertSame(0.0, $results[0]['freight_value']);
        $this->assertSame(2, $results[0]['deadline']);
    }

    public function test_rank_flags_best_price_deadline_and_cost_benefit(): void
    {
        $carriers = [
            $this->carrier('carrier-1', 'Cara'),
            $this->carrier('carrier-2', 'Media'),
            $this->carrier('carrier-3', 'Barata'),
        ];
        $engine = $this->engine(
            $carriers,
            fn($companyId, $carrierId) => $this->table([$this->route('Londrina', 2, [
                ['start' => 0, 'end' => 100, 'value' => match ($carrierId) {
                    'carrier-1' => 300,
                    'carrier-2' => 200,
                    default => 100,
                }, 'deadline' => 2],
            ])], carrierId: $carrierId),
        );

        $results = $engine->process($this->quotation());

        $byName = collect($results)->keyBy('carrier_name');
        $this->assertTrue($byName['Barata']['best_price'] ?? false);
        $this->assertTrue($byName['Barata']['best_deadline'] ?? false);
        $this->assertTrue($byName['Barata']['best_cost_benefit'] ?? false);
        $this->assertArrayNotHasKey('best_price', $byName['Cara']);
    }

    public function test_rank_with_tied_values_flags_only_one(): void
    {
        $carriers = [
            $this->carrier('carrier-1', 'Igual A'),
            $this->carrier('carrier-2', 'Igual B'),
        ];
        $engine = $this->engine($carriers, fn() => $this->table([$this->route()]));

        $results = $engine->process($this->quotation());

        $this->assertCount(2, $results);
        $this->assertCount(1, collect($results)->filter(fn($r) => ($r['best_price'] ?? false) === true));
    }

    public function test_process_without_carriers_returns_empty(): void
    {
        $engine = $this->engine([]);

        $this->assertSame([], $engine->process($this->quotation()));
    }

    public function test_inactive_carrier_is_skipped(): void
    {
        $engine = $this->engine(
            [$this->carrier('carrier-1', 'Inativa', CarrierStatus::INATIVA)],
            $this->table([$this->route()]),
        );

        $this->assertSame([], $engine->process($this->quotation()));
    }

    public function test_carrier_without_matching_table_is_skipped(): void
    {
        $engine = $this->engine([$this->carrier('carrier-1', 'SemTabela')], null);

        $this->assertSame([], $engine->process($this->quotation()));
    }
}