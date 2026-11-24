<?php

namespace App\Enums;

enum CustomerSubscriptionStatus: string
{
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('Paid'),
        };
    }
}
