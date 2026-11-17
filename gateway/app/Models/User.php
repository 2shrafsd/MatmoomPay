<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantRole;
use App\Enums\TeamNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_platform_admin
 * @property Carbon|null $banned_at
 * @property string|null $ban_reason
 * @property string|null $locale
 * @property array<string, bool>|null $notification_preferences
 * @property int|null $current_merchant_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Merchant|null $currentMerchant
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Per-request cache of the user's role in each merchant, keyed by merchant id.
     *
     * @var array<int, MerchantRole|null>
     */
    protected array $resolvedRoles = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'banned_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Merchant, $this>
     */
    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class)->withPivot('role')->withTimestamps();
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function currentMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'current_merchant_id');
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function roleIn(Merchant $merchant): ?MerchantRole
    {
        if (array_key_exists($merchant->id, $this->resolvedRoles)) {
            return $this->resolvedRoles[$merchant->id];
        }

        $role = $this->merchants()->whereKey($merchant->getKey())->value('merchant_user.role');

        return $this->resolvedRoles[$merchant->id] = is_string($role) ? MerchantRole::tryFrom($role) : null;
    }

    public function forgetResolvedRoles(): void
    {
        $this->resolvedRoles = [];
    }

    public function switchMerchant(Merchant $merchant): bool
    {
        if ($this->roleIn($merchant) === null) {
            return false;
        }

        $this->current_merchant_id = $merchant->id;
        $this->save();
        $this->setRelation('currentMerchant', $merchant);

        return true;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Emails and queued notifications go out in the language the user picked in the app.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function wantsNotification(TeamNotification $type): bool
    {
        return (bool) ($this->notification_preferences[$type->value] ?? $type->enabledByDefault());
    }
}
