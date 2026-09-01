<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, QuotationResult> $results
 */
class Quotation extends Model
{
    use TenantScoped;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'nf_number',
        'sender_cnpj',
        'receiver_cnpj',
        'origin_cep',
        'destination_cep',
        'origin_city',
        'destination_city',
        'destination_state',
        'weight',
        'boxes',
        'volume',
        'cargo_value',
        'status',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(QuotationResult::class);
    }
}
