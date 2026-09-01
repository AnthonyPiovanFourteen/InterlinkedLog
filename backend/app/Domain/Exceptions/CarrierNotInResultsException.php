<?php

namespace App\Domain\Exceptions;

class CarrierNotInResultsException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Transportadora não encontrada nos resultados');
    }
}
