<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
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
        'module',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }
}
