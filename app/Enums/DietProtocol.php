<?php

namespace App\Enums;

enum DietProtocol: string
{
    case Balanced = 'balanced';
    case Ketobiotic = 'ketobiotic';
    case CycleSync = 'cycle_sync';
    case Thyroid = 'thyroid';
    case SickleCellWarrior = 'sickle_cell_warrior';

    public function label(): string
    {
        return match ($this) {
            self::Balanced => __('Balanced Protocol'),
            self::Ketobiotic => __('Ketobiotic'),
            self::CycleSync => __('Cycle sync'),
            self::Thyroid => __('Thyroid Protocol'),
            self::SickleCellWarrior => __('Sickle cell warrior'),
        };
    }

    public static function tryFromStored(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Balanced;
        }

        $aliases = [
            'ketogenic' => self::Ketobiotic,
            'sickle_cell' => self::SickleCellWarrior,
        ];

        if (isset($aliases[$value])) {
            return $aliases[$value];
        }

        return self::tryFrom($value) ?? self::Balanced;
    }
}
