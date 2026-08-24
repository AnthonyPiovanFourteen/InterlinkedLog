<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use TenantScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'user_name',
        'level',
        'event',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }
}
