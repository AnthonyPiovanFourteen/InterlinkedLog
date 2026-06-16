<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreightTableFee extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'freight_table_id',
        'fee_type',
        'value',
        'is_percentage',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }

    public function freightTable()
    {
        return $this->belongsTo(FreightTable::class);
    }
}
