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

    /**
     * Chaves de segredo que este gateway exige na credencial. Declarado pelo
     * adaptador, não pelo controller: quem sabe o que a API precisa é quem
     * fala com ela.
     *
     * @return array<int,string>
     */
    public function requiredSecrets(): array;

    /**
     * O que cada segredo é, em português, para a interface orientar quem
     * configura. Declarado pelo adaptador pelo mesmo motivo de requiredSecrets:
     * quem sabe o que 'documento_devedor' significa é quem fala com a API.
     *
     * @return array<string,string> chave do segredo => explicação
     */
    public function secretHints(): array;

    /**
     * Documentação oficial da API, para quem estiver configurando conferir os
     * campos na fonte. Null quando a transportadora não publica documentação
     * aberta.
     */
    public function documentationUrl(): ?string;

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
