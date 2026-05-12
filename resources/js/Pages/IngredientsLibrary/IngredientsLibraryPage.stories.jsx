import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import { IngredientsLibraryPageView } from './IngredientsLibraryPage.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const Z = () => ({
    vitA: 0,
    vitB6: 0,
    vitB9: 0,
    vitB12: 0,
    vitC: 0,
    vitD: 0,
    vitE: 0,
    vitK: 0,
    calcium: 0,
    iron: 0,
    magnesium: 0,
    potassium: 0,
    zinc: 0,
    sodium: 0,
    sugar: 0,
    fiber: 0,
});

/** Compact rows: mix of Vegetables and Spices so the category filter is obvious in Storybook. */
function ingredientRow(/** @type {Record<string, unknown>} */ partial) {
    return {
        highlights: [],
        calories: 12,
        protein: 1,
        carbs: 2,
        fat: 0.2,
        ...Z(),
        ...partial,
    };
}

const VEGETABLE_NAMES = ['Spinach, raw', 'Kale, chopped', 'Carrots, raw', 'Bell pepper, red', 'Broccoli', 'Cucumber'];
const SPICE_NAMES = ['Cumin, ground', 'Paprika', 'Turmeric', 'Black pepper', 'Cinnamon', 'Coriander seed'];

const sampleIngredientsMix = [
    ...VEGETABLE_NAMES.map((name, i) =>
        ingredientRow({
            id: `veg-${i + 1}`,
            name,
            category: 'Vegetables',
            fdc: String(160000 + i),
        }),
    ),
    ...SPICE_NAMES.map((name, i) =>
        ingredientRow({
            id: `spice-${i + 1}`,
            name,
            category: 'Spices',
            fdc: String(170000 + i),
        }),
    ),
];

const VEG_COUNT = VEGETABLE_NAMES.length;
const SPICE_COUNT = SPICE_NAMES.length;

/** Extra rows for scroll + sticky header overlap in the fullscreen canvas. */
const sampleIngredientsScroll = [
    ...sampleIngredientsMix,
    ...Array.from({ length: 24 }, (_, i) =>
        ingredientRow({
            id: `bulk-${i + 1}`,
            name: `Bulk veg item ${i + 1}`,
            category: 'Vegetables',
            fdc: String(180000 + i),
        }),
    ),
];

const csvUrls = { csvTemplateUrl: '#', csvExportUrl: '#', csvImportUrl: '#' };

function IngredientsStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.ingredientDb);
    return (
        <AdminLayout
            pageTitle="Ingredients Library"
            activePath={activePath}
            onNavigate={setActivePath}
            showSearch={false}
            hidePageTitle={false}
            contentWrapperClassName={WIDE}
        >
            {children}
        </AdminLayout>
    );
}

export default {
    title: 'MealCraft/Pages/Admin/IngredientsLibrary',
    component: IngredientsLibraryPageView,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
    argTypes: {
        initialSelectedCategory: {
            control: 'select',
            options: ['All categories', 'Vegetables', 'Spices'],
            description: 'Initial library category filter (Storybook demo prop).',
        },
    },
};

export const Default = {
    render: () => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView ingredients={sampleIngredientsMix} {...csvUrls} />
        </IngredientsStoryShell>
    ),
};

/** Use Controls → `initialSelectedCategory` to snap the table to Vegetables or Spices. */
export const CategoryFilterControls = {
    args: {
        initialSelectedCategory: 'All categories',
    },
    render: (/** @type {{ initialSelectedCategory?: string }} */ args) => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView
                ingredients={sampleIngredientsMix}
                initialSelectedCategory={args.initialSelectedCategory ?? 'All categories'}
                {...csvUrls}
            />
        </IngredientsStoryShell>
    ),
};

/**
 * Opens the real category dropdown, chooses Spices, and checks the table row count.
 * (Listbox is portaled to `document.body`, so we query globally after open.)
 */
export const CategoryFilterPlay = {
    render: () => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView ingredients={sampleIngredientsMix} {...csvUrls} />
        </IngredientsStoryShell>
    ),
    play: async ({ canvasElement }) => {
        const doc = canvasElement.ownerDocument;
        const labels = Array.from(canvasElement.querySelectorAll('label'));
        const catLabel = labels.find((l) => (l.textContent ?? '').includes('Ingredient category'));
        const tid = catLabel?.getAttribute('for');
        const trigger = tid ? doc.getElementById(tid) : null;
        if (!trigger) {
            throw new Error('Category filter trigger not found');
        }
        trigger.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        await new Promise((r) => {
            setTimeout(r, 80);
        });

        const listbox = doc.querySelector('ul[role="listbox"][aria-label="Filter by ingredient category"]');
        const options = Array.from(listbox?.querySelectorAll('button[role="option"]') ?? []);
        const spicesBtn = options.find((b) => (b.textContent ?? '').trim().startsWith('Spices'));
        if (!spicesBtn) {
            throw new Error('Spices option not found in category listbox');
        }
        spicesBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        await new Promise((r) => {
            setTimeout(r, 80);
        });

        const rows = canvasElement.querySelectorAll('tbody tr');
        if (rows.length !== SPICE_COUNT) {
            throw new Error(`Expected ${SPICE_COUNT} rows after Spices filter, got ${rows.length}`);
        }
    },
};

/** Starts on Vegetables only — quick visual proof without opening Controls. */
export const StartsOnVegetables = {
    render: () => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView
                ingredients={sampleIngredientsMix}
                initialSelectedCategory="Vegetables"
                {...csvUrls}
            />
        </IngredientsStoryShell>
    ),
    play: async ({ canvasElement }) => {
        const rows = canvasElement.querySelectorAll('tbody tr');
        if (rows.length !== VEG_COUNT) {
            throw new Error(`Expected ${VEG_COUNT} rows for Vegetables preset, got ${rows.length}`);
        }
    },
};

/** Tall table so content scrolls under the sticky admin header (matches app chrome + main padding). */
export const ScrollUnderStickyBar = {
    render: () => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView ingredients={sampleIngredientsScroll} {...csvUrls} />
        </IngredientsStoryShell>
    ),
};
