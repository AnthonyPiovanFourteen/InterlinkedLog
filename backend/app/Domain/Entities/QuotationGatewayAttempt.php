<?php

namespace App\Domain\Entities;

/** Estado da consulta a uma transportadora dentro de uma cotação. */
class QuotationGatewayAttempt
{
    public const PENDING = 'pendente';

    /** Devolveu preço. */
    public const QUOTED = 'cotada';

    /** Resposta legítima de negócio: não faz esse trecho. Não é erro. */
    public const NOT_SERVED = 'nao_atende';

    /** Falha da transportadora: timeout, 5xx, limite de requisições. */
    public const UNAVAILABLE = 'indisponivel';

    /** Falha nossa ou de configuração: payload, parsing, credencial. */
    public const ERROR = 'erro';

    public function __construct(
        public readonly ?string $id,
        public readonly string $quotationId,
        public readonly string $gateway,
        public readonly string $status = self::PENDING,
        public readonly ?string $message = null,
    ) {}

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
