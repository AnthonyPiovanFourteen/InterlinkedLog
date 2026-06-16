<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationResult extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'quotation_id',
        'carrier_id',
        'carrier_name',
        'freight_value',
        'fees',
        'final_value',
        'deadline',
        'fees_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'fees_breakdown' => 'array',
        ];
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
