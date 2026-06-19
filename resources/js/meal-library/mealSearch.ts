/**
 * Meal picker search helpers for the Smart Scheduler combobox.
 * Category values align with `App\Enums\RecipeCategory` (Title Case in DB).
 */

export type MealPickerOption = {
    id: number;
    name: string;
    category: string;
};

/** Breakfast slots — `RecipeCategory::Breakfast` */
export const SCHEDULER_BREAKFAST_CATEGORIES = ['Breakfast'] as const;

/** Main meal slots — `RecipeCategory::Meal` */
export const SCHEDULER_MAIN_MEAL_CATEGORIES = ['Meal'] as const;

/** Side salad slots — `RecipeCategory::SideSalad` */
export const SCHEDULER_SIDE_SALAD_CATEGORIES = ['Side Salad'] as const;

/** Dessert slots — `RecipeCategory::Dessert` */
export const SCHEDULER_DESSERT_CATEGORIES = ['Dessert'] as const;

/** Soup slot — `RecipeCategory::Soup` */
export const SCHEDULER_SOUP_CATEGORIES = ['Soup'] as const;

/** @deprecated Use category-specific scheduler constants per slot section. */
export const SCHEDULER_MEAL_CHOICE_CATEGORIES = ['Meal', 'Side Salad', 'Dessert', 'Soup'] as const;

export type SchedulerSlotSection = {
    key: string;
    slotType: 'breakfast' | 'main' | 'salad' | 'dessert' | 'soup';
    label: string;
    count: number;
    categories: readonly string[];
};

/** Matches {@see App\Enums\MealPlanSlotType::daySlotTemplate()} slot layout. */
export const SCHEDULER_SLOT_SECTIONS: readonly SchedulerSlotSection[] = [
    { key: 'breakfast', slotType: 'breakfast', label: 'Breakfasts', count: 2, categories: SCHEDULER_BREAKFAST_CATEGORIES },
    { key: 'meal', slotType: 'main', label: 'Meal choices', count: 4, categories: SCHEDULER_MAIN_MEAL_CATEGORIES },
    { key: 'sidesalad', slotType: 'salad', label: 'Side salads', count: 2, categories: SCHEDULER_SIDE_SALAD_CATEGORIES },
    { key: 'dessert', slotType: 'dessert', label: 'Desserts', count: 2, categories: SCHEDULER_DESSERT_CATEGORIES },
    { key: 'soup', slotType: 'soup', label: 'Soup', count: 2, categories: SCHEDULER_SOUP_CATEGORIES },
];

export type SchedulerCategoryFilter =
    | typeof SCHEDULER_BREAKFAST_CATEGORIES
    | typeof SCHEDULER_MEAL_CHOICE_CATEGORIES
    | readonly string[];

/**
 * @param {string} category
 */
export function mealCategoryBadgeLabel(category: string): string {
    const normalized = category.trim();
    if (!normalized) {
        return '';
    }

    return normalized;
}

/**
 * Client-side filter when meals are already loaded (Storybook / offline).
 */
export function filterMealsForCombobox(
    meals: readonly MealPickerOption[],
    rawQuery: string,
    categories: readonly string[],
    limit = 12,
): MealPickerOption[] {
    const q = rawQuery.trim().toLowerCase();
    if (!q) {
        return [];
    }

    const allowed = new Set(categories.map((c) => c.toLowerCase()));

    return meals
        .filter((meal) => {
            const category = String(meal.category ?? '').toLowerCase();
            if (!allowed.has(category)) {
                return false;
            }
            return meal.name.toLowerCase().includes(q);
        })
        .sort((a, b) => a.name.localeCompare(b.name))
        .slice(0, limit);
}
