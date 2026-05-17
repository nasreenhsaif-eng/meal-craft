/** Variants aligned with {@link ../Components/MealSystem/CategoryBadges.jsx} / Storybook `CategoryBadges`. */
export type MealCategoryBadgeVariant = 'breakfast' | 'meal' | 'soup' | 'sideSalad' | 'dessert';

export type MealCategoryBadgeProps = {
    variant: MealCategoryBadgeVariant;
    label?: string;
};

/**
 * Maps library / CSV category strings to CategoryBadge `variant` (+ optional `label`).
 */
export function resolveMealCategoryBadgeProps(
    raw: string | null | undefined,
): MealCategoryBadgeProps {
    const text = String(raw ?? '').trim();
    if (!text) {
        return { variant: 'meal' };
    }

    const key = text.toLowerCase().replace(/\s+/g, ' ');

    const variantByCategory: Record<string, MealCategoryBadgeVariant> = {
        breakfast: 'breakfast',
        meal: 'meal',
        soup: 'soup',
        dessert: 'dessert',
        'side salad': 'sideSalad',
        sidesalad: 'sideSalad',
    };

    const variant = variantByCategory[key];
    if (variant) {
        return { variant };
    }

    if (key === 'main salad') {
        return { variant: 'meal', label: 'Main Salad' };
    }

    return { variant: 'meal', label: text };
}
