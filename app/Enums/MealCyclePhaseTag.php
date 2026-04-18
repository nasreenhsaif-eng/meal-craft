<?php

namespace App\Enums;

enum MealCyclePhaseTag: string
{
    case Menstrual = 'menstrual';
    case Follicular = 'follicular';
    case Ovulatory = 'ovulatory';
    case Luteal = 'luteal';

    public function label(): string
    {
        return match ($this) {
            self::Menstrual => __('Menstrual'),
            self::Follicular => __('Follicular'),
            self::Ovulatory => __('Ovulatory'),
            self::Luteal => __('Luteal'),
        };
    }

    /**
     * Short highlight when tags are set manually (no numeric recap).
     */
    public function compatibilityHighlight(): string
    {
        return match ($this) {
            self::Menstrual => __('Supports blood loss recovery and energy; vitamin C helps iron absorption.'),
            self::Follicular => __('Supports follicle development and skin health.'),
            self::Ovulatory => __('Fiber and B6 help the liver process estrogen surges.'),
            self::Luteal => __('Magnesium and zinc may ease PMS and support progesterone balance.'),
        };
    }

    /**
     * @return list<string>
     */
    public static function storageValues(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
