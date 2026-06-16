<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreightTableWeightRange extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'freight_table_route_id',
        'min_weight',
        'max_weight',
        'freight_value',
        'deadline_days',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function route()
    {
        return $this->belongsTo(FreightTableRoute::class, 'freight_table_route_id');
    }
}
