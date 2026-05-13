import type { MealDetailModel } from './MealDetailView';

export const mealDetailViewFixture: MealDetailModel = {
    description:
        'Seared salmon over lemon herb quinoa with roasted asparagus. Bright citrus, tender fish, and nutty grains come together for a balanced plate that supports recovery and steady energy.',
    cyclePhase: 'Ovulatory',
    dietaryTags: ['Hormone Feast', 'High Protein', 'Gluten-Free', 'Dairy-Free'],
    safetyAlerts: [
        { label: 'Fish', variant: 'allergy' },
        { label: 'Sesame', variant: 'allergy' },
    ],
    nutritionalData: {
        valueColumnLabel: 'Total (meal)',
        sections: [
            {
                title: 'Macros',
                rows: [
                    { label: 'Total calories', value: '612' },
                    { label: 'Protein (g)', value: '42.5', valueClass: 'text-[#916A00]' },
                    { label: 'Fats (g)', value: '22.0', valueClass: 'text-[#2F4C9B]' },
                    { label: 'Net carbs (g)', value: '28.4', valueClass: 'text-[#8F55A8]' },
                    { label: 'Fiber (g)', value: '6.2' },
                    { label: 'Sugar (g)', value: '4.1' },
                ],
            },
            {
                title: 'Vitamins',
                rows: [
                    { label: 'Vitamin A (mcg RAE)', value: '580' },
                    { label: 'Vitamin C (mg)', value: '38.2' },
                    { label: 'Vitamin D (mcg)', value: '12.4' },
                    { label: 'Vitamin E (mg)', value: '4.2' },
                    { label: 'Vitamin K (mcg)', value: '210' },
                    { label: 'Folate B9 (mcg)', value: '186' },
                    { label: 'Vitamin B12 (mcg)', value: '4.8' },
                    { label: 'Vitamin B6 (mg)', value: '1.1' },
                ],
            },
            {
                title: 'Minerals',
                rows: [
                    { label: 'Calcium (mg)', value: '120' },
                    { label: 'Iron (mg)', value: '3.4' },
                    { label: 'Magnesium (mg)', value: '128' },
                    { label: 'Potassium (mg)', value: '980' },
                    { label: 'Zinc (mg)', value: '2.9' },
                    { label: 'Sodium (mg)', value: '420' },
                ],
            },
        ],
    },
    ingredients: [
        '6 oz wild-caught salmon fillet, patted dry',
        '1 cup cooked quinoa, cooled slightly',
        '1 bunch asparagus, woody ends trimmed',
        '2 tbsp extra-virgin olive oil',
        '1 lemon — zest and 2 tbsp juice',
        '2 cloves garlic, minced',
        '2 tbsp fresh dill, chopped',
        'Sea salt and cracked black pepper',
    ],
    instructions: [
        'Heat the oven to 425°F (220°C). Toss asparagus with half the oil, salt, and pepper; roast 12–14 minutes until tender-crisp.',
        'Season salmon on both sides. Sear skin-side down in an oven-safe skillet over medium-high heat until the skin is crisp, about 4 minutes.',
        'Flip, add garlic and lemon zest to the pan; transfer to the oven for 6–8 minutes until the salmon flakes easily.',
        'Fold quinoa with lemon juice, dill, remaining oil, and seasoning. Plate quinoa, top with salmon and asparagus, and finish with extra dill.',
    ],
};
