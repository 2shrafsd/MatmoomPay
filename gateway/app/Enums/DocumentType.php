<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case CommercialRegistration = 'commercial_registration';
    case OwnerId = 'owner_id';
    case BankLetter = 'bank_letter';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CommercialRegistration => __('Commercial registration certificate'),
            self::OwnerId => __('Owner ID or passport'),
            self::BankLetter => __('Bank letter confirming the IBAN'),
            self::Other => __('Other document'),
        };
    }

    /**
     * Documents a merchant must provide before approval.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::CommercialRegistration, self::OwnerId, self::BankLetter];
    }
}
