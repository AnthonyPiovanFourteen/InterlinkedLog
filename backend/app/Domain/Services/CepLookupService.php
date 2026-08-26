<?php

namespace App\Domain\Services;

interface CepLookupService
{
    /**
     * @return array{0: string, 1: string}|null [cidade, UF] ou null quando o CEP não é resolvido
     */
    public function lookup(string $cep): ?array;
}