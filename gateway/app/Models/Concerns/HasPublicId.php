<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\PublicId;

/**
 * Gives a model a random, prefixed public identifier used for routing and API output.
 * Internal auto-increment keys never leave the application.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', $model->newPublicId());
            }
        });
    }

    public function newPublicId(): string
    {
        return PublicId::make($this->publicIdPrefix(), $this->publicIdLength());
    }

    abstract protected function publicIdPrefix(): string;

    protected function publicIdLength(): int
    {
        return 24;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
