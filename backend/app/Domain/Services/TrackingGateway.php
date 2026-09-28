<?php

namespace App\Domain\Services;

use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\Contract;

/**
 * Porta para consulta de rastreio na API da transportadora.
 *
 * Separada de CarrierGateway porque nem toda transportadora que cota também
 * rastreia, e vice-versa — e porque as credenciais podem diferir.
 */
interface TrackingGateway
{
    public function name(): string;

    /**
     * Eventos do contrato, do mais antigo para o mais recente.
     *
     * Cada item: ['external_id','title','date','time'] — o external_id impede
     * que o sincronismo insira o mesmo evento duas vezes.
     *
     * @return array<int,array{external_id:string,title:string,date:string,time:string}>
     */
    public function events(Contract $contract, CarrierCredential $credential): array;
}
