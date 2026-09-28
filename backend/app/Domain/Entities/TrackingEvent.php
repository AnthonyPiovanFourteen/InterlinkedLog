<?php

namespace App\Domain\Entities;

class TrackingEvent
{
    /** Digitado pela equipe. */
    public const ORIGIN_MANUAL = 'manual';

    /** Sincronizado da API da transportadora. */
    public const ORIGIN_API = 'api';

    public function __construct(
        public readonly ?string $id,
        public readonly string $contractId,
        public readonly string $title,
        public readonly string $date,
        public readonly string $time,
        public readonly ?string $observation,
        public readonly string $createdAt = '',
        public readonly string $origin = self::ORIGIN_MANUAL,
        /** Chave do evento na transportadora: impede duplicar no sincronismo. */
        public readonly ?string $externalId = null,
    ) {}

    public static function create(
        string $id, string $contractId, string $title, string $date, string $time, ?string $observation = null,
        string $origin = self::ORIGIN_MANUAL, ?string $externalId = null,
    ): self {
        return new self(
            id: $id,
            contractId: $contractId,
            title: $title,
            date: $date,
            time: $time,
            observation: $observation,
            origin: $origin,
            externalId: $externalId,
        );
    }
}
