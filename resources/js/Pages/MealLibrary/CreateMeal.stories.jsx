/**
 * Create-meal modal: fuzzy ingredient combobox, auto totals from library nutrition, taxonomy + save (Storybook uses `onCreateMealSubmit`).
 */
import { MealLibraryPageContent } from './MealLibraryPage.jsx';
import { MealLibraryStoryShell } from './MealLibraryPage.stories.jsx';

const micro = { zinc: 0, fiber: 0, sugar: 0, calcium: 0, potassium: 0, sodium: 0, vitamin_c: 0, vitamin_a: 0, vitamin_e: 0, vitamin_d: 0, vitamin_k: 0 };

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
        const spy = { called: false, /** @type {FormData|null} */ fd: null };
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
                        onCreateMealSubmit={(fd) => {
                            spy.called = true;
                            spy.fd = fd;
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
        if (!spy?.called || !spy.fd) {
            throw new Error('onCreateMealSubmit should have been called with FormData');
        }
        if (!(spy.fd instanceof FormData)) {
            throw new Error('Expected FormData');
        }
        if (spy.fd.get('name') !== 'Storybook combo meal') {
            throw new Error(`Expected name in FormData, got ${spy.fd.get('name')}`);
        }
        if (spy.fd.get('total_calories') !== '101') {
            throw new Error(`Expected total_calories 101, got ${spy.fd.get('total_calories')}`);
        }
        if (spy.fd.get('ingredients[0][ingredient_id]') !== '502') {
            throw new Error(`Expected ingredients[0][ingredient_id] 502, got ${spy.fd.get('ingredients[0][ingredient_id]')}`);
        }
        if (!String(spy.fd.get('ingredients[0][name]') ?? '').includes('Wild Rice')) {
            throw new Error('Expected ingredients[0][name] to include Wild Rice');
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
