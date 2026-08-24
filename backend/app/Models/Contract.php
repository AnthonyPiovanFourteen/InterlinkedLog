<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use TenantScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'quotation_id',
        'carrier_id',
        'carrier_name',
        'nf_number',
        'origin_city',
        'destination_city',
        'destination_state',
        'freight_value',
        'fees',
        'final_value',
        'deadline',
        'status',
        'document_number',
        'cte_number',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }
}
