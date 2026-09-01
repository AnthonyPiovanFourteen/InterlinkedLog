<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Carrier $carrier
 * @property-read Collection<int, FreightTableRoute> $routes
 * @property-read Collection<int, FreightTableFee> $fees
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

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(FreightTableRoute::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(FreightTableFee::class);
    }
}
