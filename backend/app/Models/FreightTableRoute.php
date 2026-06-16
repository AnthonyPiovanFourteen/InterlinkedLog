<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreightTableRoute extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'freight_table_id',
        'origin_city',
        'origin_uf',
        'destination_city',
        'destination_uf',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function weightRanges()
    {
        return $this->hasMany(FreightTableWeightRange::class);
    }

    public function freightTable()
    {
        return $this->belongsTo(FreightTable::class);
    }
}
