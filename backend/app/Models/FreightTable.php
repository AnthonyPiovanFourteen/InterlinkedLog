<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Carrier $carrier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FreightTableRoute> $routes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FreightTableFee> $fees
 */
class FreightTable extends Model
{
    use TenantScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
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

    public function carrier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function routes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FreightTableRoute::class);
    }

    public function fees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FreightTableFee::class);
    }
}
