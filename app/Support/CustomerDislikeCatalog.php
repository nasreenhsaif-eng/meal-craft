<?php

namespace App\Support;

/**
 * Canonical customer food dislikes for onboarding multi-select.
 */
final class CustomerDislikeCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function toDropdownOptions(): array
    {
        return [
            ['value' => 'cilantro', 'label' => __('Cilantro')],
            ['value' => 'mushrooms', 'label' => __('Mushrooms')],
            ['value' => 'olives', 'label' => __('Olives')],
            ['value' => 'spicy_food', 'label' => __('Spicy food')],
            ['value' => 'seafood', 'label' => __('Seafood')],
            ['value' => 'red_meat', 'label' => __('Red meat')],
            ['value' => 'onions', 'label' => __('Onions')],
            ['value' => 'garlic', 'label' => __('Garlic')],
            ['value' => 'bell_peppers', 'label' => __('Bell peppers')],
            ['value' => 'eggplant', 'label' => __('Eggplant')],
        ];
    }
}
