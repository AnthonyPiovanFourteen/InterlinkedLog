<?php

namespace App\Domain\Services;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\CarrierQuote;
use App\Domain\Entities\Quotation;

/**
 * Porta para cotação ao vivo na API da transportadora. Cada transportadora tem
 * um adaptador em Infrastructure/Gateways que traduz o formato dela para
 * CarrierQuote — camada anticorrupção: o XML/JSON de cada uma morre no
 * adaptador e não vaza para o domínio.
 */
interface CarrierGateway
{
    /** Identificador estável do gateway, usado em log e configuração. */
    public function name(): string;

    /** Se este gateway atende a transportadora informada. */
    public function supports(Carrier $carrier): bool;

    /**
     * Cota, ou devolve null quando a transportadora não atende a rota.
     *
     * Falha de rede, timeout ou resposta inválida devem lançar
     * CarrierGatewayException — nunca derrubar a cotação inteira.
     */
    public function quote(Quotation $quotation, Carrier $carrier, CarrierCredential $credential): ?CarrierQuote;
}
