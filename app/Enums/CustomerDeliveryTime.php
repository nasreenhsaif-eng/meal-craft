<?php

namespace App\Enums;

enum CustomerDeliveryTime: string
{
    case EarlyMorning = '05:00-07:00';
    case Morning = '07:00-09:00';
    case LateMorning = '09:00-11:00';
    case Evening = '17:00-19:00';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::EarlyMorning => __('5:00–7:00 AM'),
            self::Morning => __('7:00–9:00 AM'),
            self::LateMorning => __('9:00–11:00 AM'),
            self::Evening => __('5:00–7:00 PM'),
            self::Flexible => __('Flexible'),
        };
    }
}
