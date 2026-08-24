<?php

namespace Tests\Feature;

use App\Models\Contract;

class ContractConcurrencyTest extends ApiTestCase
{
    public function test_two_contract_attempts_for_same_quotation_result_in_exactly_one_contract(): void
    {
        $quotation = $this->createQuotation();
        $carrierId = $quotation['results'][0]['carrier_id'];

        $payload = [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $carrierId,
        ];

        $first = $this->postJson('/api/v1/contracts', $payload, $this->authHeaders());
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/contracts', $payload, $this->authHeaders());
        $second->assertStatus(422);
        $this->assertSame('Cotação não está mais válida', $second->json('message'));

        $this->assertSame(1, Contract::where('quotation_id', $quotation['id'])->count());
    }
}