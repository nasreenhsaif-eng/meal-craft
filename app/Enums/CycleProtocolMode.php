<?php

namespace App\Enums;

enum CycleProtocolMode: string
{
    case NonFasting = 'non_fasting';
    case Fasting = 'fasting';

    public function label(): string
    {
        return match ($this) {
            self::NonFasting => __('Cycle-Synced Nutrition Only (Non-Fasting)'),
            self::Fasting => __('Cycle-Synced Nutrition + Fasting'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::NonFasting => __('Nutrition Only (Non-Fasting)'),
            self::Fasting => __('Nutrition + Fasting'),
        };
    }

    public function isFastingEnabled(): bool
    {
        return $this === self::Fasting;
    }

    public static function default(): self
    {
        return self::NonFasting;
    }

    public static function tryFromStored(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return self::default();
        }

        return self::tryFrom($value) ?? self::default();
    }
}
