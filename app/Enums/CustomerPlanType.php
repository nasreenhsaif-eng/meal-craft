<?php

namespace App\Enums;

enum CustomerPlanType: string
{
    case Full = 'full';
    case Day = 'day';
    case Afternoon = 'afternoon';
    case Intermittent = 'intermittent';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Full => __('Full Craft'),
            self::Day => __('Day Craft'),
            self::Afternoon => __('Afternoon Craft'),
            self::Intermittent => __('Intermittent Craft'),
            self::Business => __('Business Craft'),
        };
    }
}
