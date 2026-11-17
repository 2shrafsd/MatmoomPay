<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $legal_name
 * @property string $activity
 * @property string|null $commercial_registration
 * @property string $email
 * @property string $phone
 * @property string|null $website
 * @property string $country
 * @property string|null $settlement_bank_name
 * @property string|null $settlement_account_name
 * @property string|null $settlement_iban
 * @property CarbonImmutable|null $settlement_account_verified_at
 * @property int|null $settlement_account_verified_by
 * @property MerchantStatus $status
 * @property string|null $status_reason
 * @property int $fee_rate_bps
 * @property int $fee_fixed
 * @property CarbonImmutable|null $approved_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'name', 'legal_name', 'activity', 'commercial_registration', 'email', 'phone', 'website', 'country',
    'settlement_bank_name', 'settlement_account_name', 'settlement_iban',
])]
#[Hidden(['settlement_iban'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchantStatus::class,
            'settlement_iban' => 'encrypted',
            'fee_rate_bps' => 'integer',
            'fee_fixed' => 'integer',
            'approved_at' => 'datetime',
            'settlement_account_verified_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'mch_';
    }

    protected function publicIdLength(): int
    {
        return 16;
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<WebhookEndpoint, $this>
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Settlement, $this>
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * @return HasMany<MerchantDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(MerchantDocument::class);
    }

    /**
     * @return HasMany<MerchantInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(MerchantInvitation::class);
    }

    public function roleFor(User $user): ?MerchantRole
    {
        $member = $this->users()->whereKey($user->getKey())->first();

        if ($member === null) {
            return null;
        }

        /** @var object{role: string} $pivot */
        $pivot = $member->getRelationValue('pivot');

        return MerchantRole::tryFrom($pivot->role);
    }

    public function maskedIban(): ?string
    {
        if (blank($this->settlement_iban)) {
            return null;
        }

        return str_repeat('•', 4).' '.substr((string) $this->settlement_iban, -4);
    }

    public function canCollect(bool $livemode): bool
    {
        if ($this->trashed()) {
            return false;
        }

        return $livemode ? $this->status->canProcessLivePayments() : $this->status->canUseTestMode();
    }

    public function hasVerifiedSettlementAccount(): bool
    {
        return filled($this->settlement_iban) && $this->settlement_account_verified_at !== null;
    }

    /**
     * Owners and admins: the people told about payouts, status changes and account security.
     *
     * @return Collection<int, User>
     */
    public function managers(): Collection
    {
        return $this->users()->wherePivotIn('role', [MerchantRole::Owner->value, MerchantRole::Admin->value])->get();
    }

    /**
     * The activity is stored in English (the API and exports need a stable value) and shown in the reader's language.
     */
    public function activityLabel(): string
    {
        return __($this->activity);
    }
}
