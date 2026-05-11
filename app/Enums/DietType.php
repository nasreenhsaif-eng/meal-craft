<?php

namespace App\Enums;

enum DietType: string
{
    case Balanced = 'balanced';
    case Keto = 'keto';
    case IntermittentFasting = 'intermittent_fasting';

    public function label(): string
    {
        return match ($this) {
            self::Balanced => __('Balanced'),
            self::Keto => __('Keto'),
            self::IntermittentFasting => __('Intermittent fasting'),
        };
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
