<?php

namespace Tests\Feature;

use App\Domain\Entities\Quotation as QuotationEntity;
use App\Domain\Services\QuotationEngineService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollationMatchingTest extends ApiTestCase
{
    private function buildQuotation(string $destinationCity): QuotationEntity
    {
        $userId = User::where('email', 'admin@interlinked.io')->first()->id;

        return new QuotationEntity(
            id: Str::orderedUuid()->toString(),
            companyId: $this->adminCompanyId,
            userId: $userId,
            nfNumber: '000900',
            senderCnpj: '12.345.678/0001-99',
            receiverCnpj: '98.765.432/0001-88',
            originCep: '01000-000',
            destinationCep: '17500-000',
            originCity: 'São Paulo',
            destinationCity: $destinationCity,
            destinationState: 'SP',
            weight: 45,
            boxes: 10,
            volume: 0.15,
            cargoValue: 5000,
            status: QuotationEntity::STATUS_VALID,
            results: [],
            validUntil: now()->addDays(7)->format('Y-m-d'),
        );
    }

    private function resultForCarrier(array $results, string $carrierId): ?array
    {
        return collect($results)->first(fn($r) => $r['carrier_id'] === $carrierId);
    }

    public function test_exact_accented_city_matches_in_any_database(): void
    {
        $carrier = $this->createCarrier(['name' => 'Transportadora Acentuada']);
        $this->createFreightTable($carrier['id'], [
            'routes' => [['city' => 'Marília', 'state' => 'SP', 'deadline' => 2]],
        ]);

        $results = app(QuotationEngineService::class)->process($this->buildQuotation('Marília'));

        $this->assertNotNull($this->resultForCarrier($results, $carrier['id']));
    }

    public function test_city_without_accent_matching_documents_collation_decision(): void
    {
        $carrier = $this->createCarrier(['name' => 'Transportadora Acentuada']);
        $this->createFreightTable($carrier['id'], [
            'routes' => [['city' => 'Marília', 'state' => 'SP', 'deadline' => 2]],
        ]);

        $results = app(QuotationEngineService::class)->process($this->buildQuotation('Marilia'));

        if (DB::connection()->getDriverName() === 'mysql') {
            // DECISÃO (Fase 2): utf8mb4_unicode_ci é accent-insensitive — 'Marilia'
            // DEVE casar com a rota 'Marília' no motor de cotação.
            $this->assertNotNull(
                $this->resultForCarrier($results, $carrier['id']),
                'No MySQL (utf8mb4_unicode_ci), a cidade sem acento deve casar com a rota acentuada',
            );
        } else {
            // SQLite (legado): LIKE é case-insensitive apenas para ASCII — não casa.
            $this->assertNull(
                $this->resultForCarrier($results, $carrier['id']),
                'No SQLite, a cidade sem acento não casa com a rota acentuada',
            );
        }
    }
}