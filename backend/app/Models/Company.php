<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'cnpj',
        'type',
        'phone',
        'email',
        'city',
        'uf',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
