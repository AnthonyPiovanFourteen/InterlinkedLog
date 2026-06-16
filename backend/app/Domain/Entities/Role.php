<?php

namespace App\Domain\Entities;

class Role
{
    public const ADMIN = 'Admin';
    public const USUARIO = 'Usuário';

    public static function all(): array
    {
        return [
            self::ADMIN,
            self::USUARIO,
        ];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all());
    }
}
