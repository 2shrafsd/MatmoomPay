<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $merchant_id
 * @property bool $livemode
 * @property string $key
 * @property string $request_fingerprint
 * @property int|null $response_status
 * @property string|null $response_body
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['merchant_id', 'livemode', 'key', 'request_fingerprint', 'response_status', 'response_body', 'expires_at'])]
class IdempotencyKey extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
