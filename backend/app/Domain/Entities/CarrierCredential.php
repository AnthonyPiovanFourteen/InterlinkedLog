<?php

namespace App\Domain\Entities;

/**
 * Credencial de acesso à API de uma transportadora, por tenant.
 *
 * Mesma decisão de freight_tables (Fase 1): a transportadora é catálogo global,
 * mas o contrato — e portanto a credencial e o preço — é de cada empresa.
 */
class CarrierCredential
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $companyId,
        public readonly string $carrierId,
        public readonly string $gateway,
        /** @var array<string,string> segredos; nunca exposto em resposta ou log */
        public readonly array $secrets,
        public readonly bool $active = true,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {}

    public function secret(string $key): ?string
    {
        return $this->secrets[$key] ?? null;
    }
}
