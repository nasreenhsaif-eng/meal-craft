<?php

namespace App\Enums;

enum CustomerGoal: string
{
    case LoseWeight = 'lose_weight';
    case Maintain = 'maintain';
    case GainMuscle = 'gain_muscle';

    public function label(): string
    {
        return match ($this) {
            self::LoseWeight => __('Lose weight'),
            self::Maintain => __('Maintain weight'),
            self::GainMuscle => __('Gain muscle'),
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
