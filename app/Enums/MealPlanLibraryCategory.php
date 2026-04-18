<?php

namespace App\Enums;

enum MealPlanLibraryCategory: string
{
    case Balanced = 'balanced';
    case SickleCellWarrior = 'sickle_cell_warrior';
    case CycleSync = 'cycle_sync';

    public function label(): string
    {
        return match ($this) {
            self::Balanced => __('Balanced'),
            self::SickleCellWarrior => __('Sickle Cell Warrior Plan'),
            self::CycleSync => __('Cycle Sync Meal Plan'),
        };
    }
}
