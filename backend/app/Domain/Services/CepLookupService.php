<?php

namespace App\Domain\Services;

interface CepLookupService
{
    /**
     * @return array{0: string, 1: string}|null [cidade, UF] ou null quando o CEP não é resolvido
     */
    public function lookup(string $cep): ?array;

    /**
     * Endereço completo, para APIs que exigem mais que cidade e UF (a Loggi
     * pede logradouro e bairro). 'logradouro' e 'bairro' podem vir vazios
     * quando o CEP é resolvido pelo mapa local, que só guarda cidade e UF.
     *
     * @return array{cep: string, logradouro: string, bairro: string, cidade: string, uf: string}|null
     */
    public function lookupAddress(string $cep): ?array;
}
