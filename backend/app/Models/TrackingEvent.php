<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_id',
        'title',
        'date',
        'time',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }
}
