<?php

namespace App\Domain\Exceptions;

class QuotationNotFoundException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Cotação não encontrada');
    }
}
