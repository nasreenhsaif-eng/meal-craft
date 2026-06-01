<?php

namespace App\Enums;

enum CustomerActivityLevel: string
{
    case Sedentary = 'sedentary';
    case LightlyActive = 'lightly_active';
    case ModeratelyActive = 'moderately_active';
    case VeryActive = 'very_active';

    /** @deprecated Use {@see self::LightlyActive} */
    case Light = 'light';

    /** @deprecated Use {@see self::ModeratelyActive} */
    case Moderate = 'moderate';

    /** @deprecated Use {@see self::ModeratelyActive} */
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::Sedentary => __('Not active'),
            self::LightlyActive, self::Light, self::Moderate => __('Somewhat active'),
            self::ModeratelyActive, self::Active => __('Highly active'),
            self::VeryActive => __('Extremely active'),
        };
    }

    public function multiplierKey(): string
    {
        return match ($this) {
            self::Sedentary => 'sedentary',
            self::LightlyActive, self::Light, self::Moderate => 'lightly_active',
            self::ModeratelyActive, self::Active => 'moderately_active',
            self::VeryActive => 'very_active',
        };
    }

    public static function tryFromStored(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::LightlyActive;
        }

        return self::tryFrom($value) ?? match ($value) {
            'lightly_active' => self::LightlyActive,
            'moderately_active' => self::ModeratelyActive,
            default => self::LightlyActive,
        };
    }
}
