<?php

namespace App\Enums;

enum CyclePhase: string
{
    case Menstrual = 'menstrual';
    case Follicular = 'follicular';
    case Ovulatory = 'ovulatory';
    case Luteal = 'luteal';

    /**
     * Resolve a CSV / UI token to a phase (enum value or English label, case-insensitive).
     */
    public static function tryFromCsvToken(string $raw): ?self
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $t) === 0) {
                return $case;
            }
        }

        $english = [
            'menstrual' => self::Menstrual,
            'follicular' => self::Follicular,
            'ovulatory' => self::Ovulatory,
            'luteal' => self::Luteal,
        ];
        $norm = strtolower(preg_replace('/\s+/', ' ', $t) ?? $t);
        if (isset($english[$norm])) {
            return $english[$norm];
        }

        foreach (self::cases() as $case) {
            $label = match ($case) {
                self::Menstrual => 'Menstrual',
                self::Follicular => 'Follicular',
                self::Ovulatory => 'Ovulatory',
                self::Luteal => 'Luteal',
            };
            if (strcasecmp($label, $t) === 0) {
                return $case;
            }
        }

        return null;
    }

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
