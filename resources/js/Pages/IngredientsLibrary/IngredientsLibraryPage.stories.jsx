/**
 * Library layout in Storybook matches the app: AdminLayout sticky title → Create + CSV row → full-width search
 * (filters by name, USDA category, and SC highlights) → ingredients table.
 */
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

/** 260 rows, 65 per USDA-style category, so search-by-category is easy to verify. */
const SEARCH_DEMO_CATEGORIES = ['Vegetables', 'Spices', 'Proteins', 'Grains'];
const LARGE_LIST_SIZE = 260;
const ROWS_PER_CATEGORY = LARGE_LIST_SIZE / SEARCH_DEMO_CATEGORIES.length;
/** Matches `PAGE_SIZE` in IngredientsLibraryPage.jsx (max visible table rows). */
const INGREDIENTS_PAGE_SIZE = 50;

const sampleIngredientsLarge = Array.from({ length: LARGE_LIST_SIZE }, (_, i) => {
    const cat = SEARCH_DEMO_CATEGORIES[i % SEARCH_DEMO_CATEGORIES.length];
    const highlights = i % 17 === 0 ? ['Folate', 'Iron'] : [];
    return ingredientRow({
        id: `bulk-ing-${i + 1}`,
        name: `${cat} sample ${i + 1}`,
        category: cat,
        fdc: String(210000 + i),
        highlights,
    });
});

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

/** Sets a controlled React input value (works with the library TextInput). */
function setInputValue(input, value) {
    const proto = window.HTMLInputElement.prototype;
    const desc = Object.getOwnPropertyDescriptor(proto, 'value');
    if (desc?.set) {
        desc.set.call(input, value);
    } else {
        input.value = value;
    }
    input.dispatchEvent(new InputEvent('input', { bubbles: true }));
}

export default {
    title: 'MealCraft/Pages/Admin/IngredientsLibrary',
    component: IngredientsLibraryPageView,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
        docs: {
            description: {
                component:
                    'Vertical order: sticky page title → Create + CSV → full-width search (name, USDA category, SC highlights) → table. No category dropdown; type e.g. “Vegetables” or “Folate” to filter.',
            },
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

/** 260 ingredients: typing a category name in search filters the table (same logic as production). */
export const SearchFiltersLargeList = {
    render: () => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView ingredients={sampleIngredientsLarge} {...csvUrls} />
        </IngredientsStoryShell>
    ),
    play: async ({ canvasElement }) => {
        const input = canvasElement.querySelector('#ingredients-library-search');
        if (!(input instanceof HTMLInputElement)) {
            throw new Error('Search input #ingredients-library-search not found');
        }
        setInputValue(input, 'Vegetables');
        await new Promise((r) => {
            setTimeout(r, 120);
        });
        const rows = canvasElement.querySelectorAll('tbody tr');
        const expectedVegetableVisible = Math.min(ROWS_PER_CATEGORY, INGREDIENTS_PAGE_SIZE);
        if (rows.length !== expectedVegetableVisible) {
            throw new Error(
                `Expected ${expectedVegetableVisible} visible rows when searching "Vegetables", got ${rows.length}`,
            );
        }
        setInputValue(input, 'Folate');
        await new Promise((r) => {
            setTimeout(r, 120);
        });
        const folateRows = canvasElement.querySelectorAll('tbody tr');
        const folateFiltered = sampleIngredientsLarge.filter((r) =>
            Array.isArray(r.highlights) ? r.highlights.some((h) => String(h).toLowerCase().includes('folate')) : false,
        ).length;
        const expectedFolateVisible = Math.min(folateFiltered, INGREDIENTS_PAGE_SIZE);
        if (folateRows.length !== expectedFolateVisible) {
            throw new Error(`Expected ${expectedFolateVisible} visible rows when searching "Folate", got ${folateRows.length}`);
        }
    },
};

export const ScrollUnderStickyBar = {
    render: () => (
        <IngredientsStoryShell>
            <IngredientsLibraryPageView ingredients={sampleIngredientsScroll} {...csvUrls} />
        </IngredientsStoryShell>
    ),
};
