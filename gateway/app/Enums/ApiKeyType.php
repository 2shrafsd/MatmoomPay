<?php

declare(strict_types=1);

namespace App\Enums;

enum ApiKeyType: string
{
    case Secret = 'secret';
    case Publishable = 'publishable';

    public function prefix(bool $livemode): string
    {
        $type = $this === self::Secret ? 'sk' : 'pk';

        return $type.'_'.($livemode ? 'live' : 'test').'_';
    }

    public function label(): string
    {
        return match ($this) {
            self::Secret => __('Secret key'),
            self::Publishable => __('Publishable key'),
        };
    }
}
