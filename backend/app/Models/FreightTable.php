<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreightTable extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'carrier_id',
        'name',
        'valid_from',
        'valid_until',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function routes()
    {
        return $this->hasMany(FreightTableRoute::class);
    }

    public function fees()
    {
        return $this->hasMany(FreightTableFee::class);
    }
}
