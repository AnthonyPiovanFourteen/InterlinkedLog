<?php

namespace App\Domain\Exceptions;

class QuotationNotValidException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Cotação não está mais válida');
    }
}