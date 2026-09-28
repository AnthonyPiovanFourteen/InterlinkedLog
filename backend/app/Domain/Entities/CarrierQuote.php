<?php

namespace App\Domain\Entities;

/**
 * Cotação devolvida por uma transportadora via API, normalizada para o formato
 * que o motor já produz a partir das tabelas locais.
 *
 * Transportadora que só devolve o total (Braspress, Jadlog) deixa
 * feesBreakdown vazio: não se inventa composição que a origem não informou.
 */
class CarrierQuote
{
    public function __construct(
        public readonly string $carrierId,
        public readonly string $carrierName,
        public readonly float $freightValue,
        public readonly float $fees,
        public readonly float $finalValue,
        public readonly int $deadline,
        public readonly array $feesBreakdown = [],
        public readonly ?string $protocol = null,
    ) {}

    public function toResult(): array
    {
        return [
            'carrier_id' => $this->carrierId,
            'carrier_name' => $this->carrierName,
            'deadline' => $this->deadline,
            'freight_value' => round($this->freightValue, 2),
            'fees' => round($this->fees, 2),
            'final_value' => round($this->finalValue, 2),
            'fees_breakdown' => $this->feesBreakdown,
            'source' => 'api',
            'protocol' => $this->protocol,
        ];
    }
}
