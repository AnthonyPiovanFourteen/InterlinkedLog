<?php

namespace App\Domain\Entities;

class Contract
{
    /** Rastreio alimentado pela API da transportadora. */
    public const TRACKING_AUTO = 'automatico';

    /** Rastreio digitado pela equipe. */
    public const TRACKING_MANUAL = 'manual';

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
        public readonly string $trackingMode = self::TRACKING_MANUAL,
        public readonly ?string $trackingGateway = null,
    ) {}

    public function isAutoTracked(): bool
    {
        return $this->trackingMode === self::TRACKING_AUTO;
    }

    public static function fromQuotation(
        string $id,
        string $documentNumber,
        string $companyId, string $quotationId, string $nfNumber,
        string $carrierId, string $carrierName,
        string $originCity, string $destinationCity, string $destinationState,
        float $freightValue, float $fees, float $finalValue, int $deadline,
        ?string $cteNumber = null,
        string $trackingMode = self::TRACKING_MANUAL,
        ?string $trackingGateway = null,
    ): self {
        return new self(
            id: $id, companyId: $companyId, quotationId: $quotationId,
            nfNumber: $nfNumber,
            carrierId: $carrierId, carrierName: $carrierName,
            originCity: $originCity, destinationCity: $destinationCity,
            destinationState: $destinationState,
            freightValue: $freightValue, fees: $fees,
            finalValue: $finalValue, deadline: $deadline,
            trackingMode: $trackingMode,
            trackingGateway: $trackingGateway,
            status: self::STATUS_SCHEDULED,
            documentNumber: $documentNumber,
            cteNumber: $cteNumber,
            cancelledAt: null, cancelReason: null,
        );
    }
}
