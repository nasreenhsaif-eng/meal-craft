<?php

namespace App\Enums;

enum MacroSplitStyle: string
{
    case Balanced = 'balanced';
    case HighProtein = 'high_protein';

    /**
     * @return array{protein_percentage: float, carb_percentage: float, fat_percentage: float}
     */
    public function percentages(): array
    {
        $presets = config('customer_nutrition.macro_presets', []);
        $key = $this->value;

        if (! isset($presets[$key])) {
            return $presets['balanced'] ?? [
                'protein_percentage' => 40.0,
                'carb_percentage' => 40.0,
                'fat_percentage' => 20.0,
            ];
        }

        return $presets[$key];
    }
}
