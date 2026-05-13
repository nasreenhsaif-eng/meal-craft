/**
 * Rich mock meals for Storybook / demos. Maps cleanly onto {@link MealCard} via the optional `meal` prop.
 *
 * @type {{
 *   title: string;
 *   imageUrl: string;
 *   category: string;
 *   prepMinutes: number;
 *   dietaryTags: string[];
 *   cyclePhase: 'Menstrual' | 'Follicular' | 'Ovulatory' | 'Luteal';
 *   safetyAlerts: { label: string; variant?: 'allergy' | 'g6pd' }[];
 *   nutritionalSummary: { calories: number; protein: string; carbs: string; fat: string };
 *   tags: { label: string; type?: string }[];
 *   dislikeTags: string[];
 * }}
 */
export const adminMealCardWithActionsFixture = {
    title: 'Lemon Chicken Quinoa',
    imageUrl: 'https://images.unsplash.com/photo-1543339308-43e59d6b73a6?auto=format&fit=crop&w=1400&q=80',
    category: 'Meal',
    prepMinutes: 35,
    dietaryTags: ['High Protein', 'Low Carbs', 'Gluten-Free'],
    cyclePhase: 'Follicular',
    safetyAlerts: [{ label: 'Shellfish', variant: 'allergy' }],
    nutritionalSummary: { calories: 610, protein: '48g', carbs: '44g', fat: '20g' },
    tags: [
        { label: 'Contains Nuts', type: 'dietary' },
        { label: 'Contains Gluten', type: 'dietary' },
    ],
    dislikeTags: ['No cilantro'],
};
