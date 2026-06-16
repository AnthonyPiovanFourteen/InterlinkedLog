<?php

namespace App\Domain\Entities;

class Contract
{
    public const STATUS_SCHEDULED = 'Agendado';
    public const STATUS_COLLECTED = 'Coletado';
    public const STATUS_IN_TRANSIT = 'Em Trânsito';
    public const STATUS_DISTRIBUTION = 'Unidade de Distribuição';
    public const STATUS_OUT_FOR_DELIVERY = 'Saiu para Entrega';
    public const STATUS_DELIVERED = 'Entregue';
    public const STATUS_CANCELLED = 'Cancelado';

    public function __construct(
        public readonly ?string $id,
        public readonly string $companyId,
        public readonly string $quotationId,
        public readonly string $nfNumber,
        public readonly string $carrierId,
        public readonly string $carrierName,
        public readonly string $originCity,
        public readonly string $destinationCity,
        public readonly string $destinationState,
        public readonly float $freightValue,
        public readonly float $fees,
        public readonly float $finalValue,
        public readonly int $deadline,
        public readonly string $status,
        public readonly string $documentNumber,
        public readonly ?string $cteNumber = null,
        public readonly ?string $cancelledAt = null,
        public readonly ?string $cancelReason = null,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public static function fromQuotation(
        string $id,
        string $documentNumber,
        string $companyId, string $quotationId, string $nfNumber,
        string $carrierId, string $carrierName,
        string $originCity, string $destinationCity, string $destinationState,
        float $freightValue, float $fees, float $finalValue, int $deadline,
        ?string $cteNumber = null,
    ): self {
        return new self(
            id: $id, companyId: $companyId, quotationId: $quotationId,
            nfNumber: $nfNumber,
            carrierId: $carrierId, carrierName: $carrierName,
            originCity: $originCity, destinationCity: $destinationCity,
            destinationState: $destinationState,
            freightValue: $freightValue, fees: $fees,
            finalValue: $finalValue, deadline: $deadline,
            status: self::STATUS_SCHEDULED,
            documentNumber: $documentNumber,
            cteNumber: $cteNumber,
            cancelledAt: null, cancelReason: null,
        );
    }
}
