<?php

declare(strict_types=1);

namespace App\Support;

final class BusinessActivities
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'retail' => 'Retail',
            'online_store' => 'Online store',
            'food' => 'Food and beverage',
            'services' => 'Professional services',
            'education' => 'Education and training',
            'health' => 'Health and wellness',
            'tourism' => 'Travel and tourism',
            'charity' => 'Charity and non-profit',
            'other' => 'Other',
        ];
    }
}
