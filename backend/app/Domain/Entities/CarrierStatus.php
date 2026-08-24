<?php

namespace App\Domain\Entities;

class CarrierStatus
{
    public const ATIVA = 'Ativa';
    public const INATIVA = 'Inativa';

    public static function all(): array
    {
        return [
            self::ATIVA,
            self::INATIVA,
        ];
    }
}