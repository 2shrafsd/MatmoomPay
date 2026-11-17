<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApiKeyType;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property ApiKeyType $type
 * @property string $name
 * @property string $token_prefix
 * @property string $token_hash
 * @property string|null $publishable_token
 * @property string $last_four
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 */
#[Fillable(['merchant_id', 'livemode', 'type', 'name', 'token_prefix', 'token_hash', 'publishable_token', 'last_four', 'last_used_at', 'revoked_at', 'created_by'])]
#[Hidden(['token_hash'])]
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use BelongsToMerchant, HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'type' => ApiKeyType::class,
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'key_';
    }

    protected function publicIdLength(): int
    {
        return 16;
    }

    /**
     * Keys are high-entropy random strings, so a keyed SHA-256 is enough and allows indexed lookups.
     */
    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function maskedToken(): string
    {
        return $this->token_prefix.str_repeat('•', 8).$this->last_four;
    }
}
