<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $company_id
 * @property string $carrier_id
 * @property string $gateway
 * @property array $secrets
 * @property bool $active
 */
class CarrierCredential extends Model
{
    use TenantScoped;

    protected $table = 'carrier_credentials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'company_id', 'carrier_id', 'gateway', 'secrets', 'active'];

    protected $hidden = ['secrets'];

    protected $casts = [
        'secrets' => 'encrypted:array',
        'active' => 'boolean',
    ];
}
