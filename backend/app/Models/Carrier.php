<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'cnpj',
        'origin_city',
        'origin_uf',
        'contact_name',
        'contact_phone',
        'contact_email',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function freightTables()
    {
        return $this->hasMany(FreightTable::class);
    }
}
