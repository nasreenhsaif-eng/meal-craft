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
