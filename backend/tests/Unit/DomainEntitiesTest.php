<?php

namespace Tests\Unit;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Contract;
use App\Domain\Entities\FreightTable;
use App\Domain\Entities\Quotation;
use App\Domain\Entities\Role;
use PHPUnit\Framework\TestCase;

class DomainEntitiesTest extends TestCase
{
    public function test_carrier_create_defaults_to_active_status(): void
    {
        $carrier = Carrier::create(
            id: 'carrier-1',
            name: 'Braspress',
            cnpj: '11.222.333/0001-44',
            originCity: 'São Paulo',
            originState: 'SP',
        );

        $this->assertSame('carrier-1', $carrier->id);
        $this->assertSame(CarrierStatus::ATIVA, $carrier->status);
        $this->assertSame('', $carrier->createdAt);
    }

    public function test_quotation_create_defaults_to_valid_status(): void
    {
        $quotation = Quotation::create(
            id: 'quote-1',
            companyId: 'company-1',
            userId: 'user-1',
            nfNumber: '000100',
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
            validUntil: '2026-12-31',
        );

        $this->assertSame(Quotation::STATUS_VALID, $quotation->status);
        $this->assertSame([], $quotation->results);
        $this->assertSame(45.0, $quotation->weight);
    }

    public function test_contract_from_quotation_defaults_to_scheduled_status(): void
    {
        $contract = Contract::fromQuotation(
            id: 'contract-1',
            documentNumber: 'CT-e 202608250001',
            companyId: 'company-1',
            quotationId: 'quote-1',
            nfNumber: '000100',
            carrierId: 'carrier-1',
            carrierName: 'Braspress',
            originCity: 'São Paulo',
            destinationCity: 'Londrina',
            destinationState: 'PR',
            freightValue: 142.0,
            fees: 10.0,
            finalValue: 152.0,
            deadline: 3,
        );

        $this->assertSame(Contract::STATUS_SCHEDULED, $contract->status);
        $this->assertSame('CT-e 202608250001', $contract->documentNumber);
        $this->assertNull($contract->cteNumber);
        $this->assertNull($contract->cancelledAt);
    }

    public function test_freight_table_create_defaults_to_draft_status(): void
    {
        $table = FreightTable::create(
            id: 'table-1',
            companyId: 'company-1',
            name: 'Tabela Geral',
            carrierId: 'carrier-1',
            originCity: 'São Paulo',
            validityStart: '2026-01-01',
            validityEnd: '2026-12-31',
        );

        $this->assertSame('Rascunho', $table->status);
        $this->assertSame([], $table->routes);
        $this->assertSame([], $table->fees);
    }

    public function test_entities_are_immutable(): void
    {
        foreach ([
            Carrier::class,
            Quotation::class,
            Contract::class,
            FreightTable::class,
        ] as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getProperties() as $property) {
                $this->assertTrue(
                    $property->isReadOnly(),
                    "{$class}::{$property->getName()} deveria ser readonly",
                );
            }
        }
    }

    public function test_carrier_status_values_and_validation(): void
    {
        $this->assertSame(['Ativa', 'Inativa'], CarrierStatus::all());
        $this->assertTrue(CarrierStatus::isValid('Ativa'));
        $this->assertTrue(CarrierStatus::isValid('Inativa'));
        $this->assertFalse(CarrierStatus::isValid('Ativo'));
    }

    public function test_role_values_and_validation(): void
    {
        $this->assertSame(['Admin', 'Usuário'], Role::all());
        $this->assertTrue(Role::isValid('Admin'));
        $this->assertTrue(Role::isValid('Usuário'));
        $this->assertFalse(Role::isValid('root'));
    }
}
