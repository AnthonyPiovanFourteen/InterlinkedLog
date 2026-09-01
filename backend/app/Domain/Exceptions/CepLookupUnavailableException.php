<?php

namespace App\Domain\Exceptions;

class CepLookupUnavailableException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Serviço de CEP indisponível no momento, tente novamente');
    }
}
