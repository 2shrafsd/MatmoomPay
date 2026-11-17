<?php

declare(strict_types=1);

namespace App\Enums;

enum CardType: string
{
    case Debit = 'Debit';
    case Credit = 'Credit';
}
