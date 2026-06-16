<?php

namespace App\Domain\Entities;

class Quotation
{
    public const STATUS_DRAFT = 'RASCUNHO';
    public const STATUS_VALID = 'VALIDA';
    public const STATUS_EXPIRED = 'EXPIRADA';
    public const STATUS_CONTRACTED = 'CONTRATADA';
    public const STATUS_CANCELLED = 'CANCELADA';

    public function __construct(
        public readonly ?string $id,
        public readonly string $companyId,
        public readonly string $userId,
        public readonly string $nfNumber,
        public readonly string $senderCnpj,
        public readonly string $receiverCnpj,
        public readonly string $originCep,
        public readonly string $destinationCep,
        public readonly string $originCity,
        public readonly string $destinationCity,
        public readonly string $destinationState,
        public readonly float $weight,
        public readonly int $boxes,
        public readonly float $volume,
        public readonly float $cargoValue,
        public readonly string $status,
        public readonly array $results,
        public readonly string $validUntil,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public static function create(
        string $id,
        string $companyId, string $userId,
        string $nfNumber, string $senderCnpj, string $receiverCnpj,
        string $originCep, string $destinationCep,
        string $originCity, string $destinationCity, string $destinationState,
        float $weight, int $boxes, float $volume, float $cargoValue,
        string $validUntil,
    ): self {
        return new self(
            id: $id, companyId: $companyId, userId: $userId,
            nfNumber: $nfNumber, senderCnpj: $senderCnpj, receiverCnpj: $receiverCnpj,
            originCep: $originCep, destinationCep: $destinationCep,
            originCity: $originCity, destinationCity: $destinationCity,
            destinationState: $destinationState,
            weight: $weight, boxes: $boxes, volume: $volume, cargoValue: $cargoValue,
            status: self::STATUS_VALID,
            results: [],
            validUntil: $validUntil,
        );
    }
}
