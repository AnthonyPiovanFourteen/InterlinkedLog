<?php

namespace Tests\Feature;

use App\Domain\Entities\FreightTable as FreightTableEntity;
use App\Domain\Entities\Quotation as QuotationEntity;
use App\Domain\Repositories\FreightTableRepository;
use App\Domain\Repositories\QuotationRepository;
use App\Models\Carrier;
use App\Models\FreightTable;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class RepositorySaveAtomicityTest extends ApiTestCase
{
    public function test_quotation_parent_is_rolled_back_when_child_writes_fail(): void
    {
        $userId = User::where('email', 'admin@interlinked.io')->first()->id;
        $id = Str::orderedUuid()->toString();

        $quotation = new QuotationEntity(
            id: $id,
            companyId: $this->adminCompanyId,
            userId: $userId,
            nfNumber: '000500',
            senderCnpj: '12.345.678/0001-99',
            receiverCnpj: '98.765.432/0001-88',
            originCep: '01000-000',
            destinationCep: '86020-000',
            originCity: 'São Paulo',
            destinationCity: 'Londrina',
            destinationState: 'PR',
            weight: 45,
            boxes: 10,
            volume: 0.15,
            cargoValue: 5000,
            status: QuotationEntity::STATUS_VALID,
            results: [[
                'carrier_id' => '00000000-0000-0000-0000-000000000000',
                'carrier_name' => 'Transportadora Inexistente',
                'freight_value' => 142.0,
                'fees' => 704.2,
                'final_value' => 846.2,
                'deadline' => 2,
                'fees_breakdown' => [],
            ]],
            validUntil: now()->addDays(7)->format('Y-m-d'),
        );

        try {
            app(QuotationRepository::class)->save($quotation);
            $this->fail('A gravação dos resultados deveria ter lançado exceção de FK');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        $this->assertDatabaseMissing('quotations', ['id' => $id]);
        $this->assertSame(0, Quotation::where('id', $id)->count());
    }

    public function test_freight_table_parent_is_rolled_back_when_child_writes_fail(): void
    {
        $carrier = Carrier::create([
            'id' => Str::orderedUuid()->toString(),
            'name' => 'Transportadora Sem Tabela',
            'cnpj' => '77.777.777/0001-77',
            'origin_city' => 'São Paulo',
            'origin_uf' => 'SP',
            'status' => 'Ativa',
        ]);
        $id = Str::orderedUuid()->toString();

        $table = new FreightTableEntity(
            id: $id,
            companyId: $this->adminCompanyId,
            name: 'Tabela Inválida',
            carrierId: $carrier->id,
            originCity: 'São Paulo',
            validityStart: '2026-01-01',
            validityEnd: '2026-12-31',
            status: 'Ativa',
            routes: [['city' => 'Londrina', 'state' => null, 'deadline' => 2]],
            weightRanges: [['start' => 0, 'end' => 100, 'value' => 100]],
            fees: [['type' => 'gris', 'value' => 10, 'percentage' => 0]],
        );

        try {
            app(FreightTableRepository::class)->save($table);
            $this->fail('A gravação das rotas deveria ter lançado exceção de NOT NULL');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        $this->assertDatabaseMissing('freight_tables', ['id' => $id]);
        $this->assertSame(0, FreightTable::where('id', $id)->count());
    }
}