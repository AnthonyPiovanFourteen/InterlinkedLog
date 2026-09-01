<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, FreightTableWeightRange> $weightRanges
 * @property-read FreightTable $freightTable
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

    public function weightRanges(): HasMany
    {
        return $this->hasMany(FreightTableWeightRange::class);
    }

    public function freightTable(): BelongsTo
    {
        return $this->belongsTo(FreightTable::class);
    }
}
