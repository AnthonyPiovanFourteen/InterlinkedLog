<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FreightTableWeightRange> $weightRanges
 * @property-read \App\Models\FreightTable $freightTable
 */
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

    public function weightRanges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FreightTableWeightRange::class);
    }

    public function freightTable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FreightTable::class);
    }
}
