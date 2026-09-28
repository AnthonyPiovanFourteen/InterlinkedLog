<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\QuotationGatewayAttempt as AttemptEntity;
use App\Domain\Repositories\QuotationGatewayAttemptRepository;
use App\Models\QuotationGatewayAttempt;
use Illuminate\Support\Str;

class EloquentQuotationGatewayAttemptRepository implements QuotationGatewayAttemptRepository
{
    public function findByQuotation(string $quotationId): array
    {
        return QuotationGatewayAttempt::where('quotation_id', $quotationId)
            ->orderBy('gateway')
            ->get()
            ->map(fn ($m) => new AttemptEntity(
                id: $m->id,
                quotationId: $m->quotation_id,
                gateway: $m->gateway,
                status: $m->status,
                message: $m->message,
            ))
            ->all();
    }

    public function save(AttemptEntity $attempt): void
    {
        QuotationGatewayAttempt::updateOrCreate(
            ['quotation_id' => $attempt->quotationId, 'gateway' => $attempt->gateway],
            [
                'id' => $attempt->id ?? Str::orderedUuid()->toString(),
                'status' => $attempt->status,
                'message' => $attempt->message,
                'updated_at' => now(),
            ]
        );
    }
}
