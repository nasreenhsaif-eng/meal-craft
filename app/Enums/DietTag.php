<?php

namespace App\Enums;

enum DietTag: string
{
    case Balanced = 'balanced';
    case Ketogenic = 'ketogenic';
    case IntermittentFasting = 'intermittent_fasting';

    public function label(): string
    {
        return match ($this) {
            self::Balanced => __('Balanced'),
            self::Ketogenic => __('Keto'),
            self::IntermittentFasting => __('Intermittent fasting'),
        };
    }

    /**
     * @return list<string>
     */
    public static function storageValues(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function toDropdownOptions(): array
    {
        return array_values(array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        ));
    }
}
