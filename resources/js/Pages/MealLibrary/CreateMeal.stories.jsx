/**
 * Create-meal modal: fuzzy ingredient combobox, auto totals from library nutrition, taxonomy + save (Storybook uses `onCreateMealSubmit`).
 */
import { MealLibraryPageContent } from './MealLibraryPage.jsx';
import { MealLibraryStoryShell } from './MealLibraryPage.stories.jsx';

const micro = { zinc: 0, fiber: 0, sugar: 0, calcium: 0, potassium: 0, sodium: 0, vitamin_c: 0, vitamin_a: 0, vitamin_e: 0, vitamin_d: 0, vitamin_k: 0 };

/** Minimal row shape for `MealLibraryPageContent` edit mode (matches server `editForm` + grid id/title). */
const editableMealFixture = {
    id: '77',
    title: 'Editable bowl',
    editForm: {
        id: '77',
        name: 'Editable bowl',
        category: 'Meal',
        mealPlanTags: ['Balanced'],
        dietTags: ['Vegan'],
        cyclePhaseValues: ['follicular'],
        description: 'Step A\nStep B',
        highlight: 'Good fuel.',
        totalCalories: '100',
        totalProtein: '3.8',
        totalCarbs: '12.5',
        totalFat: '3',
        isBulk: true,
        servingsCount: 4,
        finishedWeightGrams: '',
        imageUrl: '',
        ingredientRows: [
            {
                ingredientId: 502,
                selectedName: 'Wild Rice Blend',
                nameQuery: 'Wild Rice Blend',
                amount: '100',
                unit: 'g',
            },
        ],
    },
};

const sampleIngredientProfiles = [
    {
        id: 501,
        name: 'Brown Rice',
        calories: 111,
        protein: 2.6,
        carbs: 23,
        fat: 0.9,
        b6: 0,
        b9_folate: 0,
        b12: 0,
        iron: 0,
        magnesium: 0,
        micronutrients: { ...micro },
    },
    {
        id: 502,
        name: 'Wild Rice Blend',
        calories: 101,
        protein: 4,
        carbs: 21,
        fat: 0.3,
        b6: 0,
        b9_folate: 0,
        b12: 0,
        iron: 0,
        magnesium: 0,
        micronutrients: { ...micro },
    },
];

function setInputValue(input, value) {
    const proto = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
    proto?.set?.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

export default {
    title: 'MealCraft/Pages/Admin/MealLibrary/Create meal',
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Create New Meal: fuzzy ingredient combobox (verified library), auto-filled calories/macros from selected ingredients, meal plan tags (Balanced, Hormone Feast, Ketogenic, Sickle Cell Anemia), dietary toggles (Vegan, Vegetarian, Dairy-free, Gluten-free, Nut-free).',
            },
        },
    },
};

export const CreateMealForm = {
    name: 'Form + combobox + submit (mock)',
    render: () => {
        const spy = { called: false, /** @type {Record<string, unknown>|null} */ payload: null };
        if (typeof window !== 'undefined') {
            window.__mealCreateStorySpy = spy;
        }
        return (
            <div className="min-h-screen w-full bg-gray-50 p-8">
                <MealLibraryStoryShell>
                    <MealLibraryPageContent
                        meals={[]}
                        ingredientProfiles={sampleIngredientProfiles}
                        mealStoreUrl="#"
                        onCreateMealSubmit={(payload) => {
                            spy.called = true;
                            spy.payload = payload;
                        }}
                    />
                </MealLibraryStoryShell>
            </div>
        );
    },
    play: async ({ canvasElement }) => {
        const createBtn = [...canvasElement.querySelectorAll('button')].find((b) => /create meal/i.test(b.textContent ?? ''));
        if (!createBtn) {
            throw new Error('Create meal button not found');
        }
        createBtn.click();
        await new Promise((r) => {
            setTimeout(r, 200);
        });

        const nameInput = canvasElement.querySelector('#create-meal-name');
        if (!(nameInput instanceof HTMLInputElement)) {
            throw new Error('Expected #create-meal-name in modal');
        }
        setInputValue(nameInput, 'Storybook combo meal');

        const ingInput = canvasElement.querySelector('#ingredient-combobox-0');
        if (!(ingInput instanceof HTMLInputElement)) {
            throw new Error('Expected #ingredient-combobox-0');
        }
        ingInput.focus();
        setInputValue(ingInput, 'w ri');
        await new Promise((r) => {
            setTimeout(r, 200);
        });

        const wildBtn = [...document.querySelectorAll('button[role="option"]')].find((b) => (b.textContent ?? '').includes('Wild Rice Blend'));
        if (!wildBtn) {
            throw new Error('Expected fuzzy match option “Wild Rice Blend” (query “w ri”)');
        }
        wildBtn.click();
        await new Promise((r) => {
            setTimeout(r, 350);
        });

        const calInput = canvasElement.querySelector('#create-meal-calories');
        if (!(calInput instanceof HTMLInputElement)) {
            throw new Error('Expected #create-meal-calories');
        }
        if (calInput.value !== '101') {
            throw new Error(`Expected auto calories 101 for 100g Wild Rice Blend, got ${calInput.value}`);
        }

        const saveBtn = [...canvasElement.querySelectorAll('button')].find((b) => /^save meal$/i.test((b.textContent ?? '').trim()));
        if (!saveBtn) {
            throw new Error('Save meal button not found');
        }
        if (saveBtn.disabled) {
            throw new Error('Save meal should be enabled when name and calories are set');
        }
        saveBtn.click();
        await new Promise((r) => {
            setTimeout(r, 200);
        });

        const spy = typeof window !== 'undefined' ? window.__mealCreateStorySpy : null;
        if (!spy?.called || !spy.payload) {
            throw new Error('onCreateMealSubmit should have been called with a payload object');
        }
        if (typeof spy.payload !== 'object' || spy.payload === null || Array.isArray(spy.payload)) {
            throw new Error('Expected plain object payload');
        }
        if (spy.payload.name !== 'Storybook combo meal') {
            throw new Error(`Expected name in payload, got ${String(spy.payload.name)}`);
        }
        if (spy.payload.total_calories !== 101) {
            throw new Error(`Expected total_calories 101, got ${String(spy.payload.total_calories)}`);
        }
        const ingredients = spy.payload.ingredients;
        if (!Array.isArray(ingredients) || ingredients.length < 1) {
            throw new Error('Expected ingredients array on payload');
        }
        const first = ingredients[0];
        if (typeof first !== 'object' || first === null) {
            throw new Error('Expected first ingredient object');
        }
        if (first.ingredient_id !== 502) {
            throw new Error(`Expected ingredients[0].ingredient_id 502, got ${String(first.ingredient_id)}`);
        }
        if (!String(first.name ?? '').includes('Wild Rice')) {
            throw new Error('Expected ingredients[0].name to include Wild Rice');
        }
    },
};

const allergenStoryProfiles = [
    {
        id: 801,
        name: 'Roasted Peanut Butter',
        common_allergens: ['peanuts'],
        calories: 600,
        protein: 25,
        carbs: 20,
        fat: 50,
        b6: 0.1,
        b9_folate: 0,
        b12: 0,
        iron: 5,
        magnesium: 0,
        micronutrients: { ...micro, vitamin_c: 30 },
    },
];

export const EditMealDualActions = {
    name: 'Edit meal (Update + Save as new copy)',
    render: () => (
        <div className="min-h-screen w-full bg-gray-50 p-8">
            <MealLibraryStoryShell>
                <MealLibraryPageContent
                    meals={[]}
                    ingredientProfiles={sampleIngredientProfiles}
                    mealStoreUrl="#"
                    storyInitialCreateModalOpen
                    storyInitialMealToEdit={editableMealFixture}
                    onCreateMealSubmit={() => Promise.resolve()}
                />
            </MealLibraryStoryShell>
        </div>
    ),
    play: async ({ canvasElement }) => {
        const title = canvasElement.querySelector('#meal-library-create-title');
        if (!title || !/edit meal/i.test(title.textContent ?? '')) {
            throw new Error('Expected modal title “Edit meal”');
        }
        const updateBtn = [...canvasElement.querySelectorAll('button')].find((b) => /^update meal$/i.test((b.textContent ?? '').trim()));
        const copyBtn = [...canvasElement.querySelectorAll('button')].find((b) => /^save as new copy$/i.test((b.textContent ?? '').trim()));
        if (!updateBtn) {
            throw new Error('Update meal button not found');
        }
        if (!copyBtn) {
            throw new Error('Save as new copy button not found');
        }
        if (updateBtn.disabled || copyBtn.disabled) {
            throw new Error('Dual-action buttons should be enabled when fixture has name and calories');
        }
    },
};

export const CreateMealSafetyAutoAllergen = {
    name: 'Safety alerts (auto from allergen ingredient)',
    render: () => (
        <div className="min-h-screen w-full bg-gray-50 p-8">
            <MealLibraryStoryShell>
                <MealLibraryPageContent meals={[]} ingredientProfiles={allergenStoryProfiles} mealStoreUrl="#" />
            </MealLibraryStoryShell>
        </div>
    ),
    play: async ({ canvasElement }) => {
        const createBtn = [...canvasElement.querySelectorAll('button')].find((b) => /create meal/i.test(b.textContent ?? ''));
        if (!createBtn) {
            throw new Error('Create meal button not found');
        }
        createBtn.click();
        await new Promise((r) => {
            setTimeout(r, 200);
        });

        const ingInput = canvasElement.querySelector('#ingredient-combobox-0');
        if (!(ingInput instanceof HTMLInputElement)) {
            throw new Error('Expected #ingredient-combobox-0');
        }
        ingInput.focus();
        setInputValue(ingInput, 'pea');
        await new Promise((r) => {
            setTimeout(r, 200);
        });

        const opt = [...document.querySelectorAll('button[role="option"]')].find((b) =>
            (b.textContent ?? '').includes('Roasted Peanut Butter'),
        );
        if (!opt) {
            throw new Error('Expected combobox option Roasted Peanut Butter');
        }
        opt.click();
        await new Promise((r) => {
            setTimeout(r, 400);
        });

        const safety = canvasElement.querySelector('[aria-label="Safety alerts"]');
        if (!safety || !/Peanut/i.test(safety.textContent ?? '')) {
            throw new Error('Expected automated safety alert for peanuts in nutrition summary');
        }
    },
};
