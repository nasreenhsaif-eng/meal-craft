/**
 * @typedef {{
 *   id: string;
 *   submittedAt: string;
 *   name: string;
 *   craft: string;
 *   plan: string;
 *   breakfast: string;
 *   m1: string;
 *   m2: string;
 *   soup: string;
 *   sideSalad: string;
 *   dessert: string;
 *   allergyNotes: string;
 *   specialRequests: string;
 *   submissionId: string;
 * }} UserSubmissionRow
 */

/**
 * @typedef {{
 *   id: string;
 *   name: string;
 *   breakfast: string;
 *   m1: string;
 *   m2: string;
 *   soup: string;
 *   sideSalad: string;
 *   dessert: string;
 *   cutlery: string;
 *   kitchenInstructions?: string;
 *   specialRequests: string;
 *   allergies: string;
 * }} KitchenDailyRow
 */

/** @typedef {{ ingredient: string; unit: string; gramsPerPortion: number; portions: number; recipeLabel?: string; }} IngredientChoiceLine */

/** @type {UserSubmissionRow[]} */
export const mockUserSubmissions = [
    {
        id: 'SUB-2026-0412-001',
        submittedAt: '2026-04-12T08:14:00',
        name: 'Jordan Lee',
        craft: 'Anti-Inflammatory',
        plan: '14-Day Reset',
        breakfast: 'Oat bowl + berries',
        m1: 'Salmon bowl',
        m2: 'Lentil soup',
        soup: '',
        sideSalad: 'Mixed greens',
        dessert: '',
        allergyNotes: 'Shellfish — anaphylaxis',
        specialRequests: 'Extra ice pack for AM delivery',
        submissionId: 'SUB-2026-0412-001',
    },
    {
        id: 'SUB-2026-0412-002',
        submittedAt: '2026-04-12T09:02:00',
        name: 'Sam Rivera',
        craft: 'Balanced',
        plan: 'Weekly Standard',
        breakfast: 'Egg white scramble',
        m1: 'Chicken quinoa',
        m2: 'Veg stir-fry',
        soup: '',
        sideSalad: 'Side Caesar',
        dessert: 'Dark chocolate square',
        allergyNotes: 'None declared',
        specialRequests: '',
        submissionId: 'SUB-2026-0412-002',
    },
    {
        id: 'SUB-2026-0413-003',
        submittedAt: '2026-04-13T07:41:00',
        name: 'Avery Chen',
        craft: 'Low-FODMAP',
        plan: '14-Day Reset',
        breakfast: 'Rice porridge',
        m1: 'Rice noodle bowl',
        m2: '',
        soup: 'Miso broth cup',
        sideSalad: 'Simple greens',
        dessert: '',
        allergyNotes: 'Dairy — mild; avoid whey',
        specialRequests: 'No cilantro anywhere',
        submissionId: 'SUB-2026-0413-003',
    },
    {
        id: 'SUB-2026-0413-004',
        submittedAt: '2026-04-13T11:20:00',
        name: 'Morgan Blake',
        craft: 'Anti-Inflammatory',
        plan: 'Weekly Standard',
        breakfast: '',
        m1: 'Salmon bowl',
        m2: 'Lentil soup',
        soup: 'Carrot-ginger soup',
        sideSalad: 'Caesar (no cheese)',
        dessert: '',
        allergyNotes: 'Tree nuts',
        specialRequests: 'Without bell peppers on the side',
        submissionId: 'SUB-2026-0413-004',
    },
];

/** @type {KitchenDailyRow[]} */
export const mockKitchenDailyRows = [
    {
        id: '1',
        name: 'Jordan Lee',
        breakfast: 'Oat bowl + berries',
        m1: 'Salmon bowl',
        m2: 'Lentil soup',
        soup: '—',
        sideSalad: 'Mixed greens',
        dessert: '—',
        cutlery: 'Fork + spoon',
        kitchenInstructions:
            'breakfast: 3 eggs, vegetables: 100g, carbs: 40g\nmeal 1: chicken 120g, carbs: 180g, vegetables: 50g, sauce: 50g\nmeal 2: salmon 140g, carbs: 120g, vegetables: 60g',
        specialRequests: 'Extra ice pack for AM delivery',
        allergies: 'Shellfish — anaphylaxis',
    },
    {
        id: '2',
        name: 'Sam Rivera',
        breakfast: 'Egg white scramble',
        m1: 'Chicken quinoa',
        m2: 'Veg stir-fry',
        soup: '—',
        sideSalad: 'Side Caesar',
        dessert: 'Dark chocolate square',
        cutlery: 'Full set',
        kitchenInstructions:
            'breakfast: 2 eggs, vegetables: 80g\nmeal 1: beef 150g, carbs: 150g, vegetables: 80g',
        specialRequests: '—',
        allergies: 'None declared',
    },
    {
        id: '3',
        name: 'Avery Chen',
        breakfast: 'Rice porridge',
        m1: 'Rice noodle bowl',
        m2: '—',
        soup: 'Miso broth cup',
        sideSalad: 'Simple greens',
        dessert: '—',
        cutlery: 'Spoon only',
        kitchenInstructions: 'meal 1: chicken 90g, carbs: 180g, vegetables: 140g',
        specialRequests: 'No cilantro anywhere',
        allergies: 'Dairy — mild; avoid whey',
    },
];

/**
 * Per-portion ingredient lines used to simulate shopping math.
 * `portions` = number of guests selecting that recipe line for production day.
 *
 * @type {IngredientChoiceLine[]}
 */
export const mockIngredientChoiceLines = [
    { ingredient: 'Atlantic salmon (skin-on)', unit: 'g', gramsPerPortion: 150, portions: 10, recipeLabel: 'Salmon bowl' },
    { ingredient: 'Atlantic salmon (skin-on)', unit: 'g', gramsPerPortion: 150, portions: 3, recipeLabel: 'Salmon bowl' },
    { ingredient: 'Red lentils (dry)', unit: 'g', gramsPerPortion: 55, portions: 12, recipeLabel: 'Lentil soup' },
    { ingredient: 'Red lentils (dry)', unit: 'g', gramsPerPortion: 55, portions: 8, recipeLabel: 'Lentil soup' },
    { ingredient: 'Quinoa (dry)', unit: 'g', gramsPerPortion: 65, portions: 14, recipeLabel: 'Chicken quinoa' },
    { ingredient: 'Free-range chicken breast', unit: 'g', gramsPerPortion: 170, portions: 14, recipeLabel: 'Chicken quinoa' },
    { ingredient: 'Cod fillet', unit: 'g', gramsPerPortion: 140, portions: 6, recipeLabel: 'Baked cod' },
    { ingredient: 'Baby spinach', unit: 'g', gramsPerPortion: 40, portions: 22, recipeLabel: 'Salads / sides' },
    { ingredient: 'Olive oil', unit: 'ml', gramsPerPortion: 12, portions: 40, recipeLabel: 'Kitchen wide' },
];

/**
 * Flattened adapted meals for kitchen operation sheet recipe batches (Storybook / demos).
 *
 * @type {import('./kitchenRecipeBatches.js').AdaptedKitchenMeal[]}
 */
export const mockAdaptedKitchenMeals = [
    ...Array.from({ length: 13 }, (_, index) => ({
        mealId: 101,
        mealName: 'Salmon bowl',
        slot: 'main',
        imageUrl: null,
        instructions: '1. Season the salmon.\n2. Roast with vegetables.\n3. Plate over greens.',
        customerName: `Guest ${index + 1}`,
        ingredients: [
            { id: 1, name: 'Atlantic salmon (skin-on)', adaptedAmountGrams: 150, adaptedAmount: null, unit: 'g' },
            { id: 2, name: 'Baby spinach', adaptedAmountGrams: 40, adaptedAmount: null, unit: 'g' },
        ],
    })),
    ...Array.from({ length: 14 }, (_, index) => ({
        mealId: 202,
        mealName: 'Chicken quinoa',
        slot: 'main',
        imageUrl: null,
        instructions: '1. Cook quinoa.\n2. Sear chicken.\n3. Combine and finish with herbs.',
        customerName: `Guest ${index + 20}`,
        ingredients: [
            { id: 3, name: 'Free-range chicken breast', adaptedAmountGrams: 170, adaptedAmount: null, unit: 'g' },
            { id: 4, name: 'Quinoa (dry)', adaptedAmountGrams: 65, adaptedAmount: null, unit: 'g' },
        ],
    })),
    {
        mealId: 303,
        mealName: 'Baked cod',
        slot: 'main',
        imageUrl: null,
        instructions: '1. Bake cod until flaky.\n2. Serve with lemon.',
        customerName: 'Guest 99',
        ingredients: [{ id: 5, name: 'Cod fillet', adaptedAmountGrams: 140, adaptedAmount: null, unit: 'g' }],
    },
    {
        mealId: 303,
        mealName: 'Baked cod',
        slot: 'main',
        imageUrl: null,
        instructions: '1. Bake cod until flaky.\n2. Serve with lemon.',
        customerName: 'Guest 100',
        ingredients: [{ id: 5, name: 'Cod fillet', adaptedAmountGrams: 160, adaptedAmount: null, unit: 'g' }],
    },
];

/**
 * Prepared base recipes aggregated for the kitchen day (Storybook / demos).
 *
 * @type {import('./kitchenRecipeBatches.js').KitchenBaseRecipe[]}
 */
export const mockKitchenBaseRecipes = [
    {
        id: 901,
        name: 'Marinara Sauce (Base)',
        instructions: '1. Sweat onion and garlic.\n2. Simmer tomatoes until thick.\n3. Season and cool.',
        image_path: null,
        finished_weight_grams: 500,
        total_adapted_grams: 650,
        usage_count: 13,
        components: [
            { id: 11, name: 'Tomato', amount_grams: 400 },
            { id: 12, name: 'Onion', amount_grams: 60 },
            { id: 13, name: 'Olive oil', amount_grams: 20 },
        ],
    },
    {
        id: 902,
        name: 'Cooked Quinoa (Base)',
        instructions: '1. Rinse quinoa.\n2. Simmer until fluffy.\n3. Rest covered.',
        image_path: null,
        finished_weight_grams: 400,
        total_adapted_grams: 910,
        usage_count: 14,
        components: [
            { id: 14, name: 'Quinoa (dry)', amount_grams: 160 },
            { id: 15, name: 'Water', amount_grams: 320 },
        ],
    },
];
