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

/** Meal choice slots — main, side salad, dessert, soup */
export const SCHEDULER_MEAL_CHOICE_CATEGORIES = ['Meal', 'Side Salad', 'Dessert', 'Soup'] as const;

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
