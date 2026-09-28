<?php

namespace App\Domain\Services;

use App\Domain\Entities\Quotation;

/**
 * Modo sombra: cota nas APIs e registra a diferença contra a tabela local,
 * sem alterar o que o usuário vê.
 *
 * Existe porque ninguém acerta o mapeamento de taxas de primeira. Rodando em
 * sombra, a divergência aparece em log em vez de em cotação errada.
 */
interface CarrierQuoteShadowRunner
{
    /** @param array<int,array<string,mixed>> $localResults resultados do motor */
    public function run(Quotation $quotation, array $localResults): void;
}
