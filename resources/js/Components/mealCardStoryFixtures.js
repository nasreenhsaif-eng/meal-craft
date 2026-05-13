/**
 * Rich mock meals for Storybook / demos. Maps cleanly onto {@link MealCard} via the optional `meal` prop.
 *
 * `mushroomOmeletteAdminMealFixture` matches the **Mushroom Omelette** row in
 * `Meal_Craft_Master_Template.csv` / `public/templates/meal-craft-master-template.csv`
 * (cycle phase, dietary tags, safety alerts, calculated macros).
 *
 * @typedef {object} MealCardStoryMeal
 * @property {string} title
 * @property {string} imageUrl
 * @property {string} category
 * @property {number} prepMinutes
 * @property {string[]} dietaryTags
 * @property {'Menstrual' | 'Follicular' | 'Ovulatory' | 'Luteal'} cyclePhase
 * @property {{ label: string; variant?: 'allergy' | 'g6pd' }[]} safetyAlerts
 * @property {{ calories: number; protein: number; carbs: number; fat: number }} macros
 */

/** @type {MealCardStoryMeal} */
export const mushroomOmeletteAdminMealFixture = {
    title: 'Mushroom Omelette',
    imageUrl:
        'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=1400&q=80',
    category: 'Breakfast',
    prepMinutes: 18,
    dietaryTags: ['High protein', 'Vegetarian'],
    cyclePhase: 'Luteal',
    safetyAlerts: [
        { label: 'Eggs', variant: 'allergy' },
        { label: 'Dairy', variant: 'allergy' },
    ],
    /** Calculated Calories / Protein / Fat / Net Carbs from the master template row. */
    macros: {
        calories: 365,
        protein: 26.5,
        carbs: 5.2,
        fat: 23,
    },
};
