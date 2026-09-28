<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $quotation_id
 * @property string $gateway
 * @property string $status
 * @property string|null $message
 */
class QuotationGatewayAttempt extends Model
{
    protected $table = 'quotation_gateway_attempts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'quotation_id', 'gateway', 'status', 'message'];
}
