<?php

namespace App\Domain\Exceptions;

class CepNotFoundException extends \DomainException
{
    public function __construct(string $cep)
    {
        parent::__construct("CEP não encontrado: {$cep}");
    }
}
