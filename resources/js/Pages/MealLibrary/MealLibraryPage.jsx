import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import MealCard from '../../Components/MealCard.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import SquareCheckbox from '../../Components/Atoms/SquareCheckbox.jsx';
import NutrientBadge from '../../Components/Atoms/MealSystem/NutrientBadge.jsx';
import { calculateMealNutrition } from '../../meal-library/calculateMealNutrition.ts';
import { INGREDIENT_DATABASE, INGREDIENT_LIBRARY_ROWS } from '../../ingredient-library/mockIngredientDatabase.js';

const PAGE_BG = 'bg-[#F8F9F6]';

/** Category filter (`DropdownTextInput` story parity). */
const MEAL_TYPE_OPTIONS = ['All meal types', 'Breakfast', 'Meal', 'Soup', 'Side salad', 'Dessert'];

const MEAL_FORM_TYPE_OPTIONS = ['Breakfast', 'Meal', 'Side Salad', 'Soup', 'Dessert'];
const UNIT_OPTIONS = ['g', 'kg', 'ml', 'ltr'];
const MEAL_PLAN_TAG_OPTIONS = ['Balanced', 'Ketogenic', 'Hormone Feast', 'Sickle Cell Anemia'];

const INGREDIENT_LIBRARY = INGREDIENT_LIBRARY_ROWS;

const MOCK_MEALS = [
    {
        id: 'meal-1',
        title: 'Egg white veggie scramble',
        imageUrl:
            'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=900&q=80',
        mealType: 'Breakfast',
        category: 'Breakfast',
        prepMinutes: 18,
        macros: { calories: 312, protein: '28g', carbs: '12g', fat: '16g' },
        tags: [
            { label: 'Breakfast', type: 'category' },
            { label: 'Keto', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['B12', 'Iron'],
    },
    {
        id: 'meal-2',
        title: 'Mediterranean salmon bowl',
        imageUrl:
            'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=80',
        mealType: 'Lunch',
        category: 'Meal',
        prepMinutes: 32,
        macros: { calories: 540, protein: '42g', carbs: '38g', fat: '22g' },
        tags: [
            { label: 'Meal', type: 'category' },
            { label: 'High Protein', type: 'dietary' },
            { label: 'Low carb', type: 'dietary' },
        ],
        nutrientHighlights: ['B12', 'Magnesium'],
    },
    {
        id: 'meal-3',
        title: 'Post-training recovery shake',
        imageUrl:
            'https://images.unsplash.com/photo-1622483767028-966f0768eae5?auto=format&fit=crop&w=900&q=80',
        mealType: 'Post-Workout',
        category: 'Post-Workout',
        prepMinutes: 8,
        macros: { calories: 385, protein: '36g', carbs: '45g', fat: '8g' },
        tags: [{ label: 'Meal', type: 'category' }, { label: 'High Protein', type: 'dietary' }],
        nutrientHighlights: ['Zinc', 'Magnesium'],
    },
    {
        id: 'meal-4',
        title: 'Hearty lentil stew',
        imageUrl:
            'https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=900&q=80',
        mealType: 'Dinner',
        category: 'Soup',
        prepMinutes: 45,
        macros: { calories: 420, protein: '22g', carbs: '62g', fat: '10g' },
        tags: [
            { label: 'Soup', type: 'category' },
            { label: 'Vegan', type: 'dietary' },
            { label: 'High Protein', type: 'dietary' },
        ],
        nutrientHighlights: ['Iron', 'Folate'],
    },
    {
        id: 'meal-5',
        title: 'Greek yogurt parfait',
        imageUrl:
            'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=900&q=80',
        mealType: 'Snack',
        category: 'Meal',
        prepMinutes: 10,
        macros: { calories: 260, protein: '18g', carbs: '32g', fat: '8g' },
        tags: [{ label: 'Meal', type: 'category' }, { label: 'Keto', type: 'dietary' }],
        nutrientHighlights: ['B12', 'Zinc'],
    },
    {
        id: 'meal-6',
        title: 'Grilled chicken power plate',
        imageUrl:
            'https://images.unsplash.com/photo-1543339308-43e59d6b73a6?auto=format&fit=crop&w=900&q=80',
        mealType: 'Lunch',
        category: 'Meal',
        prepMinutes: 28,
        macros: { calories: 515, protein: '48g', carbs: '28g', fat: '20g' },
        tags: [{ label: 'Meal', type: 'category' }, { label: 'High Protein', type: 'dietary' }],
        nutrientHighlights: ['Iron', 'B12'],
    },
];

/** Delete selected — ghost/disabled styling (matches Ingredients Library). */
function deleteSelectedButtonClass(anySelected) {
    return [
        'h-[44px] min-h-[44px] rounded-[12px] px-5 text-[13px] transition-colors duration-200',
        'disabled:!cursor-not-allowed disabled:!opacity-100',
        anySelected
            ? '!bg-[#C44F5D] !text-white hover:!bg-[#B14552] hover:!text-white disabled:!opacity-100'
            : '!bg-[#C44F5D]/20 !text-[#8A8A8A] hover:!bg-[#C44F5D]/20 hover:!text-[#8A8A8A]',
    ].join(' ');
}

export default function MealLibraryPage() {
    const [query, setQuery] = useState('');
    const [categoryFilter, setCategoryFilter] = useState(MEAL_TYPE_OPTIONS[0]);
    const [meals, setMeals] = useState(() => MOCK_MEALS);
    const [selectedRows, setSelectedRows] = useState(/** @type {string[]} */ ([]));
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);

    const [formName, setFormName] = useState('');
    const [formType, setFormType] = useState('Meal');
    const [formMealPlanTag, setFormMealPlanTag] = useState('');
    const [formInstructions, setFormInstructions] = useState('');
    const [formHighlight, setFormHighlight] = useState('');
    const [formPhoto, setFormPhoto] = useState(/** @type {File|null} */ (null));
    const [finishedWeightGrams, setFinishedWeightGrams] = useState('');
    const [useAsBaseIngredient, setUseAsBaseIngredient] = useState(false);
    const [ingredientRows, setIngredientRows] = useState(
        /** @type {{ nameQuery: string; selectedName: string; amount: string; unit: string }[]} */ ([
            { nameQuery: '', selectedName: '', amount: '100', unit: 'g' },
        ]),
    );
    const [activeSuggestRow, setActiveSuggestRow] = useState(/** @type {number|null} */ (null));
    const ingredientSuggestRootRef = useRef(null);
    const [ingredientSuggestRect, setIngredientSuggestRect] = useState(
        /** @type {{ left: number; top: number; width: number } | null} */ (null),
    );

    // Close ingredient suggestion menu when clicking outside.
    // (Poka‑yoke: prevents floating menus over the form.)
    useEffect(() => {
        if (!createOpen) {
            setActiveSuggestRow(null);
        }
    }, [createOpen]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            const root = ingredientSuggestRootRef.current;
            if (!root) {
                return;
            }
            if (!root.contains(event.target)) {
                setActiveSuggestRow(null);
            }
        };
        document.addEventListener('mousedown', onDocMouseDown);
        return () => document.removeEventListener('mousedown', onDocMouseDown);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }
        if (activeSuggestRow === null) {
            setIngredientSuggestRect(null);
            return undefined;
        }

        const updateRect = () => {
            const el = document.getElementById(`ingredient-combobox-${activeSuggestRow}`);
            if (!el) {
                return;
            }
            const r = el.getBoundingClientRect();
            setIngredientSuggestRect({ left: r.left, top: r.bottom, width: r.width });
        };

        updateRect();
        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);

        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [activeSuggestRow]);

    const filteredMeals = useMemo(() => {
        const q = query.trim().toLowerCase();
        let list = meals;
        if (q) {
            list = list.filter(
                (m) =>
                    m.title.toLowerCase().includes(q) ||
                    m.tags.some((t) => String(t.label).toLowerCase().includes(q)) ||
                    m.mealType.toLowerCase().includes(q),
            );
        }
        if (categoryFilter !== 'All meal types') {
            list = list.filter((m) => m.mealType === categoryFilter);
        }
        return list;
    }, [meals, query, categoryFilter]);

    const selectedSet = useMemo(() => new Set(selectedRows), [selectedRows]);
    const anySelected = selectedRows.length > 0;

    function toggleRow(id) {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return Array.from(next);
        });
    }

    function handleConfirmDelete() {
        setMeals((prev) => prev.filter((m) => !selectedSet.has(m.id)));
        setSelectedRows([]);
        setConfirmOpen(false);
    }

    function unitToGrams(amount, unit) {
        const n = Number(amount);
        if (!Number.isFinite(n) || n <= 0) {
            return 0;
        }
        if (unit === 'kg') {
            return n * 1000;
        }
        if (unit === 'ltr') {
            return n * 1000;
        }
        return n;
    }

    const mealCsvRowForCalculator = useMemo(() => {
        const pairs = ingredientRows
            .map((r) => {
                const name = (r.selectedName || r.nameQuery || '').trim();
                const grams = unitToGrams(r.amount, r.unit);
                if (!name || grams <= 0) {
                    return null;
                }
                return `${name}:${Math.round(grams * 100) / 100}`;
            })
            .filter(Boolean);

        return {
            meal_name: formName || 'Untitled',
            category: formType,
            meal_plan_tag: formMealPlanTag,
            ingredient_quantities: pairs.join('|'),
            instructions: formInstructions,
            highlight: formHighlight,
        };
    }, [ingredientRows, formName, formType, formMealPlanTag, formInstructions, formHighlight]);

    const nutritionResult = useMemo(
        () => calculateMealNutrition(mealCsvRowForCalculator, INGREDIENT_DATABASE),
        [mealCsvRowForCalculator],
    );

    const scBadges = useMemo(() => {
        const n = nutritionResult?.nutrition ?? {};
        const badges = [];
        if ((n.b9_folate ?? 0) >= 150) badges.push('Folate');
        if ((n.b12 ?? 0) >= 1.5) badges.push('B12');
        if ((n.iron ?? 0) >= 6) badges.push('Iron');
        if ((n.magnesium ?? 0) >= 120) badges.push('Magnesium');
        if ((n.zinc ?? 0) >= 3) badges.push('Zinc');
        return badges;
    }, [nutritionResult]);

    const nutritionFullRows = useMemo(() => {
        const n = nutritionResult?.nutrition ?? {};
        const num = (v) => (typeof v === 'number' && Number.isFinite(v) ? v : 0);
        const fmt = (v, digits = 1) => num(v).toFixed(digits).replace(/\.0$/, '');

        return [
            {
                section: 'MACROS',
                rows: [
                    { label: 'Calories', value: fmt(n.calories, 0) },
                    { label: 'Protein (g)', value: fmt(n.protein, 1), valueClass: 'text-[#916A00]' },
                    { label: 'Fat (g)', value: fmt(n.fat, 1), valueClass: 'text-[#2F4C9B]' },
                    { label: 'Carbs (g)', value: fmt(n.carbs, 1), valueClass: 'text-[#8F55A8]' },
                    { label: 'Fiber (g)', value: fmt(n.fiber, 1) },
                    { label: 'Sugar (g)', value: fmt(n.sugar, 1) },
                ],
            },
            {
                section: 'SICKLE CELL HIGHLIGHTS',
                rows: [
                    { label: 'Vitamin B6 (mg)', value: fmt(n.b6, 2) },
                    { label: 'Folate B9 (mcg)', value: fmt(n.b9_folate, 1) },
                    { label: 'Vitamin B12 (mcg)', value: fmt(n.b12, 2) },
                    { label: 'Iron (mg)', value: fmt(n.iron, 2) },
                    { label: 'Magnesium (mg)', value: fmt(n.magnesium, 1) },
                ],
            },
            {
                section: 'OTHER ESSENTIAL MICROS',
                rows: [
                    { label: 'Zinc (mg)', value: fmt(n.zinc, 2) },
                    { label: 'Vitamin C (mg)', value: fmt(n.vitamin_c, 1) },
                    { label: 'Vitamin A', value: fmt(n.vitamin_a, 1) },
                    { label: 'Vitamin D', value: fmt(n.vitamin_d, 1) },
                    { label: 'Vitamin E', value: fmt(n.vitamin_e, 1) },
                    { label: 'Vitamin K', value: fmt(n.vitamin_k, 1) },
                    { label: 'Potassium (mg)', value: fmt(n.potassium, 1) },
                    { label: 'Sodium (mg)', value: fmt(n.sodium, 1) },
                    { label: 'Calcium (mg)', value: fmt(n.calcium, 1) },
                ],
            },
        ];
    }, [nutritionResult]);

    const savePayload = useMemo(() => {
        const pairs = ingredientRows
            .map((r) => {
                const name = (r.selectedName || r.nameQuery || '').trim();
                const grams = unitToGrams(r.amount, r.unit);
                if (!name || grams <= 0) {
                    return null;
                }
                return `${name}:${Math.round(grams)}`;
            })
            .filter(Boolean);
        return {
            Meal_Name: formName.trim(),
            Category: formType,
            Meal_Plan_Tag: formMealPlanTag,
            Ingredient_Quantities: pairs.join('|'),
            Instructions: formInstructions.trim(),
            Description_Highlight: formHighlight.trim(),
            Use_As_Base_Ingredient: useAsBaseIngredient,
            Photo: formPhoto ? formPhoto.name : null,
        };
    }, [ingredientRows, formName, formType, formMealPlanTag, formInstructions, formHighlight, useAsBaseIngredient, formPhoto]);

    return (
        <div className={`min-h-screen ${PAGE_BG} px-4 py-8 font-sans md:px-8`}>
            <div className="mx-auto max-w-[1400px] space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-[260px]">
                        <p className="mb-1 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                            Admin / Meal Library
                        </p>
                        <h1 className="font-montserrat text-[20px] font-bold tracking-tight text-[#262A22]">Meal Library</h1>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Browse, filter, and curate meals for Smart Kitchen workflows.
                        </p>
                    </div>
                    <Button label="Create meal" variant="primary" type="button" onClick={() => setCreateOpen(true)} />
                </div>

                <section className="rounded-[12px] border border-gray-200 bg-white shadow-sm" aria-labelledby="meal-library-heading">
                    <h2 id="meal-library-heading" className="sr-only">
                        Meal library
                    </h2>

                    <div className="relative z-20 flex flex-col gap-6 border-b border-gray-200 p-5 pt-6">
                        <div className="grid w-full gap-6 sm:grid-cols-[minmax(0,1fr)_280px] sm:items-end sm:gap-8">
                            <div className="w-full min-w-0">
                                <TextInput
                                    label="Search meals"
                                    placeholder="Search by meal name, tag, or type…"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    className="!max-w-none"
                                />
                            </div>
                            <div className="w-full min-w-0">
                                <DropdownTextInput
                                    label="Meal category"
                                    value={categoryFilter}
                                    options={MEAL_TYPE_OPTIONS}
                                    onChange={setCategoryFilter}
                                    listboxAriaLabel="Filter by meal category"
                                    className="!max-w-none"
                                />
                            </div>
                        </div>
                        <CSVUploader
                            className="pt-2"
                            templateUrl="#"
                            exportUrl="#"
                            onUpload={async (file) => {
                                // Demo-only: hook up to real processing per page later.
                                void file;
                            }}
                        />
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                        <div className="min-w-0">
                            <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">
                                Meal library
                            </p>
                            <p className="mt-0.5 font-body text-xs text-[#555555]">
                                {filteredMeals.length} shown • {selectedRows.length} selected
                            </p>
                        </div>
                        <Button
                            label="Delete selected"
                            variant="ghost"
                            type="button"
                            disabled={selectedRows.length === 0}
                            onClick={() => {
                                if (!anySelected) {
                                    return;
                                }
                                setConfirmOpen(true);
                            }}
                            className={deleteSelectedButtonClass(anySelected)}
                        />
                    </div>

                    <div className="p-5">
                        {filteredMeals.length === 0 ? (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] p-8 text-center font-body text-sm text-[#555555]">
                                No meals match your filters. Adjust search or meal category.
                            </p>
                        ) : (
                            <ul className="m-0 grid list-none grid-cols-1 justify-items-center gap-8 p-0 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                {filteredMeals.map((meal) => (
                                    <li key={meal.id} className="w-full max-w-[310px]">
                                        <MealCard
                                            variant="admin"
                                            adminControls
                                            showActions
                                            selected={selectedSet.has(meal.id)}
                                            onToggleSelected={() => toggleRow(meal.id)}
                                            onEdit={() => {}}
                                            onDelete={() => {}}
                                            title={meal.title}
                                            imageUrl={meal.imageUrl}
                                            imageAlt=""
                                            category={meal.category}
                                            prepMinutes={meal.prepMinutes}
                                            macros={meal.macros}
                                            tags={meal.tags}
                                            nutrientHighlights={meal.nutrientHighlights}
                                            primaryActionLabel="View details"
                                            onPrimaryAction={() => {}}
                                            className="transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-xl active:translate-y-0 active:scale-[0.98] active:shadow-md"
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>

            {confirmOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setConfirmOpen(false)}
                        aria-label="Close delete confirmation"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Delete selected meals"
                        className="relative w-full max-w-[480px] rounded-[12px] bg-white p-8 shadow-2xl"
                    >
                        <p className="text-center font-montserrat text-xs font-bold uppercase tracking-[0.22em] text-[#C44F5D]">
                            Warning
                        </p>
                        <p className="mt-3 text-center font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]">
                            Delete selected meals permanently?
                        </p>
                        <p className="mt-3 text-center font-body text-sm text-[#555555]">
                            This action can’t be undone in this demo. Selection will be cleared after delete.
                        </p>
                        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Button
                                label="Cancel"
                                variant="secondary"
                                type="button"
                                onClick={() => setConfirmOpen(false)}
                                className="w-full"
                            />
                            <Button
                                label="Delete permanently"
                                variant="ghost"
                                type="button"
                                className={
                                    'w-full rounded-[12px] transition-colors duration-200 ' +
                                    '!bg-[#C44F5D] !text-white hover:!bg-[#B14552] hover:!text-white'
                                }
                                onClick={handleConfirmDelete}
                            />
                        </div>
                    </div>
                </div>
            ) : null}

            {createOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setCreateOpen(false)}
                        aria-label="Close create meal modal"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Create new meal"
                        className="relative w-full max-w-[1200px] overflow-hidden rounded-[12px] bg-white shadow-2xl"
                    >
                        <div className="absolute right-3 top-3 z-20">
                            <Button label="Close" variant="ghost" onClick={() => setCreateOpen(false)} />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-[13fr_7fr]">
                            {/* Left: Form */}
                            <div className="flex max-h-[85vh] flex-col overflow-hidden border-b border-gray-200 md:border-b-0 md:border-r">
                                <div className="flex-1 overflow-y-auto px-10 pb-6 pt-10">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 className="font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]">
                                                Create New Meal
                                            </h2>
                                            <p className="mt-1 font-body text-sm text-[#555555]">
                                                Build a meal and calculate nutrition live from your ingredient library.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-6 space-y-6">
                                    <TextInput
                                        label="Meal name"
                                        placeholder="e.g. Post-training recovery shake"
                                        value={formName}
                                        onChange={(e) => setFormName(e.target.value)}
                                        className="!max-w-none"
                                    />

                                    <DropdownTextInput
                                        label="Meal type"
                                        value={formType}
                                        options={MEAL_FORM_TYPE_OPTIONS}
                                        onChange={setFormType}
                                        className="!max-w-none"
                                    />
                                    <DropdownTextInput
                                        label="Meal Plan Tag"
                                        value={formMealPlanTag}
                                        options={MEAL_PLAN_TAG_OPTIONS}
                                        onChange={setFormMealPlanTag}
                                        className="!max-w-none"
                                    />

                                    <div className="block w-full max-w-[492px] text-left !max-w-none">
                                        <label className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                            Instructions
                                        </label>
                                        <textarea
                                            value={formInstructions}
                                            onChange={(e) => setFormInstructions(e.target.value)}
                                            rows={4}
                                            className="block w-full resize-y rounded-[12px] border border-[#E5E7EB] bg-white px-4 py-3 font-body text-[15px] text-[#1F2937] shadow-sm outline-none focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                            placeholder="Steps, prep notes, cooking instructions…"
                                        />
                                    </div>

                                    <TextInput
                                        label="Description highlight"
                                        placeholder="Short Smart Kitchen note (optional)…"
                                        value={formHighlight}
                                        onChange={(e) => setFormHighlight(e.target.value)}
                                        className="!max-w-none"
                                    />

                                    <div className="block w-full text-left">
                                        <p className="mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                            Meal photo
                                        </p>
                                        <label className="relative block cursor-pointer overflow-hidden rounded-[12px] border-2 border-dashed border-gray-200 bg-[#F8F9F6] p-10">
                                            <div className="aspect-[4/3] w-full">
                                                <div className="flex h-full flex-col items-center justify-center px-6 text-center">
                                                    <p className="max-w-2xl font-montserrat text-[14px] font-bold uppercase leading-relaxed tracking-[0.10em] text-[#5A6B44]">
                                                        Upload photo (JPG, PNG, WebP, HEIC, or AVIF — max 5&nbsp;MB)
                                                    </p>
                                                    <p className="mt-6 font-body text-sm text-[#6B7280]">
                                                        {formPhoto ? formPhoto.name : 'Click to choose a file'}
                                                    </p>
                                                </div>
                                            </div>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                className="absolute inset-0 h-full w-full opacity-0"
                                                onChange={(e) => {
                                                    const f = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                                                    setFormPhoto(f);
                                                }}
                                            />
                                        </label>
                                    </div>

                                    <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-3"
                                            onClick={() => setUseAsBaseIngredient((v) => !v)}
                                            aria-pressed={useAsBaseIngredient}
                                        >
                                            <SquareCheckbox checked={useAsBaseIngredient} />
                                            <span className="font-montserrat text-sm font-bold text-[#262A22]">
                                                Use this recipe as a base ingredient
                                            </span>
                                        </button>
                                        <p className="mt-2 font-body text-sm text-[#555555]">
                                            When saved, an ingredient with the same name is created/updated using this batch’s
                                            total nutrition divided by finished weight (per 100 g). Editing the meal updates that
                                            ingredient automatically.
                                        </p>
                                    </div>

                                    <div
                                        className={[
                                            'overflow-hidden transition-all duration-300',
                                            useAsBaseIngredient ? 'max-h-40 opacity-100' : 'max-h-0 opacity-0',
                                        ].join(' ')}
                                    >
                                        <div className={['w-full', useAsBaseIngredient ? '' : 'pointer-events-none'].join(' ')}>
                                            <TextInput
                                                label="Finished weight (g)"
                                                placeholder="e.g. 800"
                                                value={finishedWeightGrams}
                                                onChange={(e) => setFinishedWeightGrams(e.target.value)}
                                                className="!max-w-none"
                                                inputMode="decimal"
                                            />
                                            <p className="mt-1 block font-body text-xs text-[#555555]">
                                                Enter the final weight after cooking (accounts for evaporation/reduction).
                                            </p>
                                        </div>
                                    </div>

                                    <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-montserrat text-sm font-bold text-[#262A22]">Ingredients</p>
                                            <Button
                                                label="Add ingredient"
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    setIngredientRows((prev) => [...prev, { nameQuery: '', selectedName: '', amount: '100', unit: 'g' }])
                                                }
                                            />
                                        </div>

                                        <div ref={ingredientSuggestRootRef} className="mt-4 space-y-4">
                                            {ingredientRows.map((row, idx) => {
                                                const q = row.nameQuery.trim().toLowerCase();
                                                const matches =
                                                    q.length < 1
                                                        ? []
                                                        : INGREDIENT_LIBRARY.filter((i) => i.name.toLowerCase().includes(q)).slice(0, 10);
                                                return (
                                                    <div key={idx} className="rounded-[12px] border border-gray-100 bg-[#F8F9F6] p-3">
                                                        <div className="grid gap-4 md:grid-cols-[1fr_100px_90px_auto] md:items-end">
                                                            <div className="relative min-w-0">
                                                                <TextInput
                                                                    id={`ingredient-combobox-${idx}`}
                                                                    label="Ingredient"
                                                                    placeholder="Type to search…"
                                                                    value={row.selectedName || row.nameQuery}
                                                                    onChange={(e) => {
                                                                        const v = e.target.value;
                                                                        setIngredientRows((prev) =>
                                                                            prev.map((r, i) =>
                                                                                i === idx ? { ...r, nameQuery: v, selectedName: '' } : r,
                                                                            ),
                                                                        );
                                                                    }}
                                                                    onFocus={() => {
                                                                        setActiveSuggestRow(idx);
                                                                    }}
                                                                    className="!max-w-none"
                                                                />
                                                                {activeSuggestRow === idx && matches.length > 0 && ingredientSuggestRect
                                                                    ? createPortal(
                                                                          <div
                                                                              className="fixed z-[90]"
                                                                              style={{
                                                                                  left: `${ingredientSuggestRect.left}px`,
                                                                                  top: `${ingredientSuggestRect.top + 8}px`,
                                                                                  width: `${ingredientSuggestRect.width}px`,
                                                                              }}
                                                                          >
                                                                              <div className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl">
                                                                                  <div className="max-h-56 overflow-auto">
                                                                                      {matches.map((m) => (
                                                                                          <button
                                                                                              key={m.name}
                                                                                              type="button"
                                                                                              className="flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors hover:bg-[#F8F9F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset"
                                                                                              onClick={() => {
                                                                                                  setIngredientRows((prev) =>
                                                                                                      prev.map((r, i) =>
                                                                                                          i === idx
                                                                                                              ? { ...r, selectedName: m.name, nameQuery: m.name }
                                                                                                              : r,
                                                                                                      ),
                                                                                                  );
                                                                                                  setActiveSuggestRow(null);
                                                                                              }}
                                                                                          >
                                                                                              <span className="min-w-0 truncate">{m.name}</span>
                                                                                              <span className="shrink-0 text-xs font-medium text-[#555555]">
                                                                                                  per 100g
                                                                                              </span>
                                                                                          </button>
                                                                                      ))}
                                                                                  </div>
                                                                              </div>
                                                                          </div>,
                                                                          document.body,
                                                                      )
                                                                    : null}
                                                            </div>

                                                            <TextInput
                                                                label="Amount"
                                                                type="number"
                                                                placeholder="100"
                                                                value={row.amount}
                                                                onChange={(e) =>
                                                                    setIngredientRows((prev) =>
                                                                        prev.map((r, i) => (i === idx ? { ...r, amount: e.target.value } : r)),
                                                                    )
                                                                }
                                                                className="!max-w-none text-center"
                                                            />

                                                            <DropdownTextInput
                                                                label="Unit"
                                                                value={row.unit}
                                                                options={UNIT_OPTIONS}
                                                                onChange={(v) =>
                                                                    setIngredientRows((prev) =>
                                                                        prev.map((r, i) => (i === idx ? { ...r, unit: v } : r)),
                                                                    )
                                                                }
                                                                className="!max-w-none text-center"
                                                            />

                                                            <div className="flex justify-end">
                                                                <PillButton
                                                                    label="Remove"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setIngredientRows((prev) =>
                                                                            prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx),
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <div className="border-t border-gray-200 bg-white px-10 pb-10 pt-6">
                                    <Button
                                        label="Save meal"
                                        variant="primary"
                                        onClick={() => {
                                            // Demo: emit payload for backend wiring.
                                            console.log('Save payload', savePayload);
                                            setCreateOpen(false);
                                        }}
                                        className="w-full justify-center"
                                    />
                                </div>
                            </div>

                            {/* Right: Nutrition Summary */}
                            <div className="max-h-[85vh] overflow-y-auto bg-[#F8F9F6] p-10">
                                <div className="sticky top-0">
                                    <div className="min-w-0 pr-12">
                                    <h3 className="font-montserrat text-[18px] font-bold tracking-tight text-[#262A22]">
                                        Nutrition Summary
                                    </h3>
                                    <p className="mt-1 font-body text-sm text-[#555555]">
                                        Live totals from selected ingredients (per 100 g library values).
                                    </p>
                                    </div>

                                    <div className="mt-4 rounded-[12px] border border-gray-200 bg-white p-4 shadow-sm">
                                        <div className="overflow-hidden rounded-[12px] border border-gray-200 bg-white">
                                            <div className="border-b border-gray-200 px-4 py-3">
                                                <p className="font-montserrat text-sm font-bold text-[#262A22]">Nutrient</p>
                                            </div>
                                            <div>
                                                {nutritionFullRows.map((section) => (
                                                    <div key={section.section}>
                                                        <div className="border-b border-gray-100 bg-[#F8F9F6] px-4 py-2">
                                                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                                {section.section}
                                                            </p>
                                                        </div>
                                                        {section.rows.map((r) => (
                                                            <div
                                                                key={`${section.section}-${r.label}`}
                                                                className="flex items-center justify-between gap-4 border-b border-gray-100 px-4 py-2"
                                                            >
                                                                <p className="font-body text-sm text-[#374151]">{r.label}</p>
                                                                <p
                                                                    className={[
                                                                        'font-montserrat text-sm font-bold tabular-nums text-[#1F2937]',
                                                                        r.valueClass ?? '',
                                                                    ].join(' ')}
                                                                >
                                                                    {r.value}
                                                                </p>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="mt-4 border-t border-gray-100 pt-4">
                                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                SC highlights
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {scBadges.length > 0 ? (
                                                    scBadges.map((b) => <NutrientBadge key={b} type={b} />)
                                                ) : (
                                                    <p className="font-body text-sm text-[#555555]">—</p>
                                                )}
                                            </div>
                                        </div>

                                        {nutritionResult.categoryWarnings?.length ? (
                                            <div className="mt-4 rounded-[12px] border border-amber-200 bg-amber-50 p-3">
                                                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-amber-900">
                                                    Warnings
                                                </p>
                                                <ul className="mt-2 list-inside list-disc space-y-1 font-body text-sm text-amber-950">
                                                    {nutritionResult.categoryWarnings.map((w) => (
                                                        <li key={w}>{w}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
