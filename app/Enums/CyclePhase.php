<?php

namespace App\Enums;

enum CyclePhase: string
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
