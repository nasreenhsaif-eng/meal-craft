import { useCallback, useEffect, useMemo, useRef, useState, Fragment } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { router, usePage } from '@inertiajs/react';
import AdminInertiaShell from '../../Layouts/AdminInertiaShell.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import MealCard from '../../Components/MealCard.jsx';
import MealListRow from '../../Components/MealListRow.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import RoundIconButton from '../../Components/Atoms/Icons/RoundIconButton.jsx';
import { IconLayoutGrid, IconLayoutList } from '../../Components/Atoms/SvgIcons.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import NutrientBadge from '../../Components/Atoms/MealSystem/NutrientBadge.jsx';
import { aggregateNutritionFromIngredientRows } from '../../meal-library/aggregateIngredientNutrition.ts';
import { calculateMealNutrition, resolveMealLibraryCategory } from '../../meal-library/calculateMealNutrition.ts';
import { filterIngredientsForCombobox } from '../../meal-library/ingredientSearch.ts';
import {
    collectSafetyAlertLabelsFromIngredientSelection,
    sickleCellProgramMealHighlight,
} from '../../meal-library/mealSafetyAndSickle.ts';
import { DIETARY_TAG_OPTIONS, MEAL_PLAN_TAG_OPTIONS } from '../../meal-library/mealTaxonomy.js';
import SafetyAlerts from '../../Components/MealSystem/SafetyAlerts.jsx';

const PAGE_BG = 'bg-[#F8F9F6]';
const PAGE_SIZE = 12;

const MEAL_FORM_TYPE_OPTIONS = ['Breakfast', 'Meal', 'Side Salad', 'Soup', 'Dessert'];
const UNIT_OPTIONS = ['g', 'kg', 'ml', 'ltr'];

const DEFAULT_CYCLE_PHASES = [
    { value: 'menstrual', label: 'Menstrual' },
    { value: 'follicular', label: 'Follicular' },
    { value: 'ovulatory', label: 'Ovulatory' },
    { value: 'luteal', label: 'Luteal' },
];

/** @param {{ value: string; label: string }[]} items */
function dropdownStringsWithBlank(items) {
    return ['', ...items.map((item) => item.label)];
}

/** @param {{ value: string; label: string }[]} items */
function valueForLabel(items, label) {
    if (!label) {
        return '';
    }
    return items.find((item) => item.label === label)?.value ?? '';
}

function fmtMacroFromNutrition(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) {
        return '';
    }
    const s = (Math.round(n * 10) / 10).toFixed(1);
    return s.replace(/\.0$/, '');
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

/**
 * @param {{
 *   cyclePhases?: { value: string; label: string }[];
 *   meals?: object[];
 *   ingredientProfiles?: object[];
 *   csvTemplateUrl?: string;
 *   csvExportUrl?: string;
 *   csvImportUrl?: string;
 *   mealStoreUrl?: string;
 *   initialViewMode?: 'grid' | 'list';
 *   flashSuccess?: string | null;
 *   flashError?: string | null;
 *   mealLibrarySchemaNotice?: string | null;
 *   onCreateMealSubmit?: (formData: FormData) => void | Promise<void>;
 * }} props
 */
export function MealLibraryPageContent({
    cyclePhases = DEFAULT_CYCLE_PHASES,
    meals = [],
    ingredientProfiles = [],
    csvTemplateUrl = '#',
    csvExportUrl = '#',
    csvImportUrl = '#',
    mealStoreUrl = '#',
    initialViewMode,
    flashSuccess = null,
    flashError = null,
    mealLibrarySchemaNotice = null,
    onCreateMealSubmit,
}) {
    const [query, setQuery] = useState('');
    const [mealRows, setMealRows] = useState(meals);
    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);
    const [selectedRows, setSelectedRows] = useState(/** @type {string[]} */ ([]));
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [viewMode, setViewMode] = useState(() => initialViewMode ?? 'grid');

    useEffect(() => {
        setMealRows(meals);
    }, [meals]);

    useEffect(() => {
        if (initialViewMode !== undefined) {
            setViewMode(initialViewMode);
        }
    }, [initialViewMode]);

    useEffect(() => {
        setVisibleCount(PAGE_SIZE);
    }, [query, mealRows]);

    const [formName, setFormName] = useState('');
    const [formType, setFormType] = useState('Meal');
    const [formMealPlanTag, setFormMealPlanTag] = useState('');
    const [formCalories, setFormCalories] = useState('');
    const [formProtein, setFormProtein] = useState('');
    const [formCarbs, setFormCarbs] = useState('');
    const [formFat, setFormFat] = useState('');
    const [selectedDietTags, setSelectedDietTags] = useState(/** @type {string[]} */ ([]));
    const [formCyclePhaseLabel, setFormCyclePhaseLabel] = useState('');
    const [formInstructions, setFormInstructions] = useState('');
    const [formHighlight, setFormHighlight] = useState('');
    const [formPhoto, setFormPhoto] = useState(/** @type {File|null} */ (null));
    const [finishedWeightGrams, setFinishedWeightGrams] = useState('');
    const [useAsBaseIngredient, setUseAsBaseIngredient] = useState(false);
    const [ingredientRows, setIngredientRows] = useState(
        /** @type {{ nameQuery: string; selectedName: string; ingredientId: number | null; amount: string; unit: string }[]} */ ([
            { nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' },
        ]),
    );
    const cyclePhaseDropdownOptions = useMemo(() => dropdownStringsWithBlank(cyclePhases), [cyclePhases]);

    const resetCreateForm = useCallback(() => {
        setFormName('');
        setFormType('Meal');
        setFormMealPlanTag('');
        setFormCalories('');
        setFormProtein('');
        setFormCarbs('');
        setFormFat('');
        setSelectedDietTags([]);
        setFormCyclePhaseLabel('');
        setFormInstructions('');
        setFormHighlight('');
        setFormPhoto(null);
        setFinishedWeightGrams('');
        setUseAsBaseIngredient(false);
        setIngredientRows([{ nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' }]);
    }, []);

    useEffect(() => {
        if (!createOpen) {
            resetCreateForm();
        }
    }, [createOpen, resetCreateForm]);

    function toggleDietTag(tag) {
        setSelectedDietTags((prev) => (prev.includes(tag) ? prev.filter((t) => t !== tag) : [...prev, tag]));
    }

    const canSave = useMemo(() => {
        const nameOk = formName.trim().length > 0;
        const cal = Number(formCalories);
        const calOk = formCalories.trim() !== '' && Number.isFinite(cal) && cal >= 0;
        return nameOk && calOk;
    }, [formName, formCalories]);

    const ingredientDatabase = useMemo(
        () =>
            (ingredientProfiles ?? []).map((p) => ({
                id: typeof p.id === 'number' ? p.id : p.id != null ? Number(p.id) : undefined,
                name: p.name,
                common_allergens: Array.isArray(p.common_allergens) ? [...p.common_allergens] : [],
                calories: p.calories,
                protein: p.protein,
                carbs: p.carbs,
                fat: p.fat,
                b6: p.b6,
                b9_folate: p.b9_folate,
                b12: p.b12,
                iron: p.iron,
                magnesium: p.magnesium,
                micronutrients: p.micronutrients ?? {},
            })),
        [ingredientProfiles],
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
            const t = event.target;
            if (!(t instanceof Node)) {
                return;
            }
            if (root.contains(t)) {
                return;
            }
            // Suggestions render in a portal on document.body; clicks must not count as "outside".
            if (t.closest('[data-meal-library-ingredient-suggest]')) {
                return;
            }
            setActiveSuggestRow(null);
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
        if (!q) {
            return mealRows;
        }
        return mealRows.filter((m) => {
            const titleMatch = String(m.title ?? '')
                .toLowerCase()
                .includes(q);
            const mealTypeMatch = String(m.mealType ?? '')
                .toLowerCase()
                .includes(q);
            const categoryMatch = String(m.category ?? '')
                .toLowerCase()
                .includes(q);
            const tagMatch =
                Array.isArray(m.tags) &&
                m.tags.some((t) => String(t.label ?? t ?? '').toLowerCase().includes(q));
            return titleMatch || mealTypeMatch || categoryMatch || tagMatch;
        });
    }, [mealRows, query]);

    const displayedMeals = useMemo(
        () => filteredMeals.slice(0, Math.min(visibleCount, filteredMeals.length)),
        [filteredMeals, visibleCount],
    );

    const selectedSet = useMemo(() => new Set(selectedRows), [selectedRows]);
    const anySelected = selectedRows.length > 0;
    const allVisibleSelected =
        displayedMeals.length > 0 && displayedMeals.every((m) => selectedSet.has(m.id));

    function toggleAllVisible() {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (allVisibleSelected) {
                displayedMeals.forEach((m) => next.delete(m.id));
            } else {
                displayedMeals.forEach((m) => next.add(m.id));
            }
            return Array.from(next);
        });
    }

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
        setMealRows((prev) => prev.filter((m) => !selectedSet.has(m.id)));
        setSelectedRows([]);
        setConfirmOpen(false);
    }

    function buildIngredientsForStore() {
        return ingredientRows
            .map((r) => {
                const name = (r.selectedName || r.nameQuery || '').trim();
                const grams = unitToGrams(r.amount, r.unit);
                if (!name || grams <= 0) {
                    return null;
                }
                const row = {
                    name,
                    amount_grams: Math.round(grams * 100) / 100,
                };
                if (r.ingredientId != null && Number.isFinite(r.ingredientId)) {
                    row.ingredient_id = r.ingredientId;
                }
                return row;
            })
            .filter(Boolean);
    }

    function handleSaveCreateMeal() {
        if (!canSave) {
            return;
        }
        const fd = new FormData();
        fd.append('name', formName.trim());
        fd.append('total_calories', String(Number(formCalories)));
        fd.append('total_protein', String(formProtein.trim() === '' ? 0 : Number(formProtein)));
        fd.append('total_carbs', String(formCarbs.trim() === '' ? 0 : Number(formCarbs)));
        fd.append('total_fat', String(formFat.trim() === '' ? 0 : Number(formFat)));
        fd.append('category', formType);
        if (formMealPlanTag) {
            fd.append('meal_plan_tag', formMealPlanTag);
        }
        selectedDietTags.forEach((t) => fd.append('diet_tags[]', t));
        const cp = valueForLabel(cyclePhases, formCyclePhaseLabel);
        if (cp) {
            fd.append('cycle_phase', cp);
        }
        fd.append('description', formInstructions);
        fd.append('highlight', formHighlight);
        if (formPhoto) {
            fd.append('photo', formPhoto);
        }
        const ings = buildIngredientsForStore();
        ings.forEach((ing, i) => {
            fd.append(`ingredients[${i}][name]`, ing.name);
            fd.append(`ingredients[${i}][amount_grams]`, String(ing.amount_grams));
            if (ing.ingredient_id != null) {
                fd.append(`ingredients[${i}][ingredient_id]`, String(ing.ingredient_id));
            }
        });
        if (useAsBaseIngredient) {
            fd.append('use_as_base_ingredient', '1');
        }
        if (finishedWeightGrams.trim() !== '') {
            fd.append('finished_weight_grams', finishedWeightGrams.trim());
        }

        if (onCreateMealSubmit) {
            void Promise.resolve(onCreateMealSubmit(fd)).then(() => setCreateOpen(false));
            return;
        }

        router.post(mealStoreUrl, fd, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setCreateOpen(false),
        });
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

        const base = {
            meal_name: formName || 'Untitled',
            meal_plan_tag: formMealPlanTag,
            cycle_phase: valueForLabel(cyclePhases, formCyclePhaseLabel),
            ingredient_quantities: pairs.join('|'),
            instructions: formInstructions,
            highlight: formHighlight,
        };
        if (resolveMealLibraryCategory(formType) !== null) {
            base.category = formType;
        }
        return base;
    }, [
        ingredientRows,
        formName,
        formType,
        formMealPlanTag,
        formCyclePhaseLabel,
        cyclePhases,
        formInstructions,
        formHighlight,
    ]);

    const nutritionResult = useMemo(
        () => calculateMealNutrition(mealCsvRowForCalculator, ingredientDatabase),
        [mealCsvRowForCalculator, ingredientDatabase],
    );

    const aggregatedIngredientNutrition = useMemo(
        () =>
            aggregateNutritionFromIngredientRows(
                ingredientRows.map((r) => ({
                    ingredientId: r.ingredientId,
                    selectedName: r.selectedName,
                    nameQuery: r.nameQuery,
                    amount: r.amount,
                    unit: r.unit,
                })),
                ingredientDatabase,
            ),
        [ingredientRows, ingredientDatabase],
    );

    const nutritionForSidebar = useMemo(() => {
        if (aggregatedIngredientNutrition.resolvedLineCount > 0) {
            return aggregatedIngredientNutrition.nutrition;
        }
        if (nutritionResult?.ok) {
            return nutritionResult.nutrition;
        }
        return null;
    }, [aggregatedIngredientNutrition, nutritionResult]);

    useEffect(() => {
        if (!nutritionForSidebar) {
            return;
        }
        const hasIngredientLines = ingredientRows.some((r) => {
            const grams = unitToGrams(r.amount, r.unit);
            const label = (r.selectedName || r.nameQuery || '').trim();
            return grams > 0 && label.length > 0;
        });
        if (!hasIngredientLines) {
            return;
        }
        const n = nutritionForSidebar;
        setFormCalories(String(Math.round(n.calories ?? 0)));
        setFormProtein(fmtMacroFromNutrition(n.protein));
        setFormCarbs(fmtMacroFromNutrition(n.carbs));
        setFormFat(fmtMacroFromNutrition(n.fat));
    }, [nutritionForSidebar, ingredientRows]);

    const safetyFormAlerts = useMemo(
        () =>
            collectSafetyAlertLabelsFromIngredientSelection(
                ingredientRows.map((r) => ({
                    ingredientId: r.ingredientId,
                    selectedName: r.selectedName,
                    nameQuery: r.nameQuery,
                })),
                ingredientDatabase,
            ),
        [ingredientRows, ingredientDatabase],
    );

    const scBadges = useMemo(() => {
        const n = nutritionForSidebar ?? {};
        const badges = [];
        if ((n.b9_folate ?? 0) >= 150) badges.push('Folate');
        if ((n.b12 ?? 0) >= 1.5) badges.push('B12');
        if ((n.iron ?? 0) >= 6) badges.push('Iron');
        if ((n.magnesium ?? 0) >= 120) badges.push('Magnesium');
        if ((n.zinc ?? 0) >= 3) badges.push('Zinc');
        if (nutritionForSidebar && sickleCellProgramMealHighlight(n)) {
            badges.push('Sickle Cell');
        }
        return badges;
    }, [nutritionForSidebar]);

    const nutritionSummarySections = useMemo(() => {
        if (!nutritionForSidebar) {
            return [];
        }
        const n = nutritionForSidebar;
        const num = (v) => (typeof v === 'number' && Number.isFinite(v) ? v : 0);
        const fmt = (v, digits = 1) => num(v).toFixed(digits).replace(/\.0$/, '');

        return [
            {
                title: 'Macros',
                rows: [
                    { label: 'Total calories', value: fmt(n.calories, 0) },
                    { label: 'Protein (g)', value: fmt(n.protein, 1), valueClass: 'text-[#916A00]' },
                    { label: 'Carbs (g)', value: fmt(n.carbs, 1), valueClass: 'text-[#8F55A8]' },
                    { label: 'Fat (g)', value: fmt(n.fat, 1), valueClass: 'text-[#2F4C9B]' },
                    { label: 'Fiber (g)', value: fmt(n.fiber, 1) },
                    { label: 'Sugar (g)', value: fmt(n.sugar, 1) },
                ],
            },
            {
                title: 'Iron & B vitamins (Sickle cell focus)',
                rows: [
                    { label: 'Iron (mg)', value: fmt(n.iron, 2) },
                    { label: 'Vitamin C (mg)', value: fmt(n.vitamin_c, 1) },
                    { label: 'Folate B9 (mcg)', value: fmt(n.b9_folate, 1) },
                    { label: 'Vitamin B12 (mcg)', value: fmt(n.b12, 2) },
                    { label: 'Vitamin B6 (mg)', value: fmt(n.b6, 2) },
                    { label: 'Magnesium (mg)', value: fmt(n.magnesium, 1) },
                    { label: 'Zinc (mg)', value: fmt(n.zinc, 2) },
                ],
            },
            {
                title: 'Other minerals',
                rows: [
                    { label: 'Calcium (mg)', value: fmt(n.calcium, 1) },
                    { label: 'Potassium (mg)', value: fmt(n.potassium, 1) },
                    { label: 'Sodium (mg)', value: fmt(n.sodium, 1) },
                ],
            },
            {
                title: 'Fat-soluble vitamins',
                rows: [
                    { label: 'Vitamin A', value: fmt(n.vitamin_a, 1) },
                    { label: 'Vitamin D', value: fmt(n.vitamin_d, 1) },
                    { label: 'Vitamin E', value: fmt(n.vitamin_e, 1) },
                    { label: 'Vitamin K', value: fmt(n.vitamin_k, 1) },
                ],
            },
        ];
    }, [nutritionForSidebar]);

    const loadMoreFooter =
        filteredMeals.length > visibleCount ? (
            <div className="flex justify-center border-t border-gray-100 pt-6">
                <Button
                    label={`Load more (${Math.min(PAGE_SIZE, filteredMeals.length - visibleCount)} meals)`}
                    variant="secondary"
                    type="button"
                    onClick={() => setVisibleCount((c) => c + PAGE_SIZE)}
                />
            </div>
        ) : null;

    return (
        <div className={`min-h-screen ${PAGE_BG} px-4 pb-8 pt-4 font-sans md:px-8`}>
            <div className="mx-auto max-w-[1400px] space-y-6">
                {flashSuccess ? (
                    <div
                        role="status"
                        className="rounded-[12px] border border-[#5A6B44]/30 bg-[#F8F9F6] px-4 py-3 font-body text-sm text-[#262A22]"
                    >
                        {flashSuccess}
                    </div>
                ) : null}
                {flashError ? (
                    <div
                        role="alert"
                        className="rounded-[12px] border border-[#C44F5D]/40 bg-[#FDF2F2] px-4 py-3 font-body text-sm text-[#7F1D1D]"
                    >
                        {flashError}
                    </div>
                ) : null}
                {mealLibrarySchemaNotice ? (
                    <div
                        role="alert"
                        className="rounded-[12px] border border-amber-300 bg-amber-50 px-4 py-3 font-body text-sm text-amber-950"
                    >
                        {mealLibrarySchemaNotice}
                    </div>
                ) : null}
                <section className="relative z-0 rounded-[12px] border border-gray-200 bg-white shadow-sm" aria-labelledby="meal-library-heading">
                    <h2 id="meal-library-heading" className="sr-only">
                        Meal library
                    </h2>
                    <p id="meal-library-desc" className="sr-only">
                        Browse, filter, and curate meals for Smart Kitchen workflows.
                    </p>

                    <div
                        className="flex w-full flex-col gap-6 rounded-t-[12px] border-b border-gray-200 px-5 pb-6 pt-6"
                        aria-describedby="meal-library-desc"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-3">
                            <div className="shrink-0">
                                <Button
                                    label="Create meal"
                                    variant="primary"
                                    type="button"
                                    className="shrink-0 uppercase tracking-wide"
                                    onClick={() => setCreateOpen(true)}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <CSVUploader
                                    className="w-full pt-0"
                                    templateUrl={csvTemplateUrl}
                                    exportUrl={csvExportUrl}
                                    onUpload={async (file) => {
                                        const formData = new FormData();
                                        formData.append('file', file);
                                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                        await axios.post(csvImportUrl, formData, {
                                            headers: {
                                                'Content-Type': 'multipart/form-data',
                                                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                                            },
                                        });
                                        router.reload();
                                    }}
                                />
                            </div>
                        </div>

                        <div className="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="min-w-0 flex-1">
                                <TextInput
                                    label="Search meals"
                                    placeholder="Search by name, meal type, category, or tag…"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    className="!max-w-none"
                                />
                            </div>
                            <div
                                className="flex shrink-0 items-center gap-1 self-stretch rounded-[12px] border border-[#E5E7EB] bg-[#F8F9F6] p-1 sm:self-auto"
                                role="group"
                                aria-label="Library view"
                            >
                                <RoundIconButton
                                    type="button"
                                    icon={<IconLayoutGrid className={viewMode === 'grid' ? 'text-[#5A6B44]' : ''} />}
                                    ariaLabel="Grid view"
                                    aria-pressed={viewMode === 'grid'}
                                    onClick={() => setViewMode('grid')}
                                    className={
                                        viewMode === 'grid'
                                            ? '!border-transparent bg-white text-[#262A22] shadow-sm'
                                            : '!border-transparent bg-transparent text-[#6B7280] shadow-none hover:bg-white/70'
                                    }
                                />
                                <RoundIconButton
                                    type="button"
                                    icon={<IconLayoutList className={viewMode === 'list' ? 'text-[#5A6B44]' : ''} />}
                                    ariaLabel="List view"
                                    aria-pressed={viewMode === 'list'}
                                    onClick={() => setViewMode('list')}
                                    className={
                                        viewMode === 'list'
                                            ? '!border-transparent bg-white text-[#262A22] shadow-sm'
                                            : '!border-transparent bg-transparent text-[#6B7280] shadow-none hover:bg-white/70'
                                    }
                                />
                            </div>
                        </div>

                        <div className="-mx-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 pb-0 pt-4">
                            <div className="min-w-0">
                                <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">Meal library</p>
                                <p className="mt-0.5 font-body text-xs text-[#555555]">
                                    <span className="font-semibold text-[#374151]">{mealRows.length}</span> in library ·{' '}
                                    {displayedMeals.length} of {filteredMeals.length} in view
                                    {filteredMeals.length < mealRows.length ? ' (filtered)' : ''} · {selectedRows.length}{' '}
                                    selected
                                    {filteredMeals.length > PAGE_SIZE && displayedMeals.length < filteredMeals.length
                                        ? ' · use "Load more" below'
                                        : ''}
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
                    </div>

                    <div className="p-5">
                        {filteredMeals.length === 0 ? (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] p-8 text-center font-body text-sm text-[#555555]">
                                No meals match your search. Try another name, type, category, or tag.
                            </p>
                        ) : viewMode === 'grid' ? (
                            <>
                                <ul className="m-0 grid list-none grid-cols-1 gap-6 p-0 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {displayedMeals.map((meal) => (
                                        <li key={meal.id} className="flex h-full min-w-0 w-full">
                                            <MealCard
                                                variant="admin"
                                                adminControls
                                                showAdminSelectionCheckbox={false}
                                                showActions
                                                title={meal.title}
                                                imageUrl={meal.imageUrl}
                                                imageAlt=""
                                                category={meal.category}
                                                prepMinutes={meal.prepMinutes}
                                                macros={meal.macros}
                                                tags={meal.tags}
                                                allergyTags={meal.safetyAlertTags ?? []}
                                                nutrientHighlights={meal.nutrientHighlights}
                                                primaryActionLabel="View details"
                                                onPrimaryAction={() => {}}
                                                onEdit={() => {}}
                                                onDelete={() => {}}
                                                className="flex-1 min-h-0 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-xl active:translate-y-0 active:scale-[0.98] active:shadow-md"
                                            />
                                        </li>
                                    ))}
                                </ul>
                                {loadMoreFooter}
                            </>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-[640px] w-full border-collapse text-[#1F2937]">
                                        <thead className="bg-white">
                                            <tr className="border-b border-gray-200">
                                                <th className="w-[52px] px-3 py-3 text-left">
                                                    <button
                                                        type="button"
                                                        onClick={toggleAllVisible}
                                                        aria-label={allVisibleSelected ? 'Deselect all visible' : 'Select all visible'}
                                                        className="inline-flex items-center rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                                    >
                                                        <SquareCheckbox checked={allVisibleSelected} />
                                                    </button>
                                                </th>
                                                <th className="min-w-0 px-4 py-3 text-left">
                                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                        Name
                                                    </span>
                                                </th>
                                                <th className="w-[120px] px-3 py-3 text-left">
                                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                        Type
                                                    </span>
                                                </th>
                                                <th className="w-[100px] px-3 py-3 text-right">
                                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                        Calories
                                                    </span>
                                                </th>
                                                <th className="min-w-[180px] px-3 py-3 text-left">
                                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                        Macros
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {displayedMeals.map((meal) => (
                                                <MealListRow
                                                    key={meal.id}
                                                    meal={meal}
                                                    selected={selectedSet.has(meal.id)}
                                                    onToggleSelected={() => toggleRow(meal.id)}
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {loadMoreFooter}
                            </>
                        )}
                    </div>
                </section>
            </div>

            {confirmOpen ? (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
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
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
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
                                        id="create-meal-name"
                                        label="Meal name"
                                        placeholder="e.g. Post-training recovery shake"
                                        value={formName}
                                        onChange={(e) => setFormName(e.target.value)}
                                        className="!max-w-none"
                                        required
                                    />

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <TextInput
                                            id="create-meal-calories"
                                            label="Calories"
                                            placeholder="e.g. 420"
                                            value={formCalories}
                                            onChange={(e) => setFormCalories(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                            required
                                        />
                                        <TextInput
                                            label="Protein (g)"
                                            placeholder="0"
                                            value={formProtein}
                                            onChange={(e) => setFormProtein(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                        />
                                        <TextInput
                                            label="Carbs (g)"
                                            placeholder="0"
                                            value={formCarbs}
                                            onChange={(e) => setFormCarbs(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                        />
                                        <TextInput
                                            label="Fat (g)"
                                            placeholder="0"
                                            value={formFat}
                                            onChange={(e) => setFormFat(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                        />
                                    </div>

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
                                        options={['', ...MEAL_PLAN_TAG_OPTIONS]}
                                        onChange={setFormMealPlanTag}
                                        className="!max-w-none"
                                    />
                                    <div className="space-y-2">
                                        <p className="font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                            Dietary tags
                                        </p>
                                        <div className="flex flex-wrap gap-3" role="group" aria-label="Dietary tags">
                                            {DIETARY_TAG_OPTIONS.map((tag) => {
                                                const checked = selectedDietTags.includes(tag);
                                                return (
                                                    <button
                                                        key={tag}
                                                        type="button"
                                                        className="inline-flex items-center gap-2 rounded-[10px] border border-[#E5E7EB] bg-[#F8F9F6] px-3 py-2 font-body text-sm text-[#262A22] transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                                        aria-pressed={checked}
                                                        onClick={() => toggleDietTag(tag)}
                                                    >
                                                        <SquareCheckbox checked={checked} presentational />
                                                        {tag}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                    <DropdownTextInput
                                        label="Cycle phase"
                                        value={formCyclePhaseLabel}
                                        options={cyclePhaseDropdownOptions}
                                        onChange={setFormCyclePhaseLabel}
                                        listboxAriaLabel="Cycle phase"
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
                                                    setIngredientRows((prev) => [
                                                        ...prev,
                                                        { nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' },
                                                    ])
                                                }
                                            />
                                        </div>

                                        <div ref={ingredientSuggestRootRef} className="mt-4 space-y-4">
                                            {ingredientRows.map((row, idx) => {
                                                const matches =
                                                    row.nameQuery.trim().length < 1
                                                        ? []
                                                        : filterIngredientsForCombobox(ingredientDatabase, row.nameQuery, 15);
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
                                                                                i === idx
                                                                                    ? { ...r, nameQuery: v, selectedName: '', ingredientId: null }
                                                                                    : r,
                                                                            ),
                                                                        );
                                                                    }}
                                                                    autoComplete="off"
                                                                    role="combobox"
                                                                    aria-expanded={activeSuggestRow === idx && matches.length > 0}
                                                                    aria-controls={
                                                                        matches.length > 0 ? `ingredient-listbox-${idx}` : undefined
                                                                    }
                                                                    aria-autocomplete="list"
                                                                    onFocus={() => {
                                                                        setActiveSuggestRow(idx);
                                                                    }}
                                                                    className="!max-w-none"
                                                                />
                                                                {activeSuggestRow === idx && matches.length > 0 && ingredientSuggestRect
                                                                    ? createPortal(
                                                                          <div
                                                                              data-meal-library-ingredient-suggest
                                                                              className="fixed z-[9999]"
                                                                              style={{
                                                                                  left: `${ingredientSuggestRect.left}px`,
                                                                                  top: `${ingredientSuggestRect.top + 8}px`,
                                                                                  width: `${ingredientSuggestRect.width}px`,
                                                                              }}
                                                                          >
                                                                              <div
                                                                                  id={`ingredient-listbox-${idx}`}
                                                                                  role="listbox"
                                                                                  className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl"
                                                                              >
                                                                                  <div className="max-h-56 overflow-auto">
                                                                                      {matches.map((m) => (
                                                                                          <button
                                                                                              key={m.id != null ? `ing-${m.id}` : m.name}
                                                                                              type="button"
                                                                                              role="option"
                                                                                              className="flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors hover:bg-[#F8F9F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset"
                                                                                              onClick={() => {
                                                                                                  setIngredientRows((prev) =>
                                                                                                      prev.map((r, i) =>
                                                                                                          i === idx
                                                                                                              ? {
                                                                                                                    ...r,
                                                                                                                    selectedName: m.name,
                                                                                                                    nameQuery: m.name,
                                                                                                                    ingredientId:
                                                                                                                        typeof m.id === 'number' &&
                                                                                                                        Number.isFinite(m.id)
                                                                                                                            ? m.id
                                                                                                                            : null,
                                                                                                                }
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
                                        type="button"
                                        disabled={!canSave}
                                        onClick={handleSaveCreateMeal}
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
                                        <div className="mb-4">
                                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                Safety alerts
                                            </p>
                                            <div className="mt-2">
                                                {safetyFormAlerts.length > 0 ? (
                                                    <SafetyAlerts
                                                        alerts={safetyFormAlerts.map((label) => ({
                                                            label,
                                                            variant: 'allergy',
                                                        }))}
                                                    />
                                                ) : (
                                                    <p className="font-body text-sm text-[#555555]">
                                                        No common-allergen ingredients detected in the current lines.
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        {nutritionSummarySections.length > 0 ? (
                                            <div className="overflow-hidden rounded-[12px] border border-gray-200 bg-white">
                                                <table className="w-full border-collapse text-left text-sm">
                                                    <thead>
                                                        <tr className="border-b border-gray-200 bg-[#F8F9F6]">
                                                            <th className="px-3 py-2 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                                Nutrient
                                                            </th>
                                                            <th className="px-3 py-2 text-right font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                                Total (meal)
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {nutritionSummarySections.map((sec) => (
                                                            <Fragment key={sec.title}>
                                                                <tr className="bg-[#F8F9F6]">
                                                                    <td
                                                                        colSpan={2}
                                                                        className="px-3 py-2 font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]"
                                                                    >
                                                                        {sec.title}
                                                                    </td>
                                                                </tr>
                                                                {sec.rows.map((r) => (
                                                                    <tr key={`${sec.title}-${r.label}`} className="border-b border-gray-100">
                                                                        <td className="px-3 py-2 font-body text-[#374151]">{r.label}</td>
                                                                        <td
                                                                            className={[
                                                                                'px-3 py-2 text-right font-montserrat text-sm font-bold tabular-nums text-[#1F2937]',
                                                                                r.valueClass ?? '',
                                                                            ].join(' ')}
                                                                        >
                                                                            {r.value}
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </Fragment>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] px-3 py-4 font-body text-sm text-[#555555]">
                                                Select verified ingredients to see weighted nutrition totals, automated safety
                                                alerts, and program highlights.
                                            </p>
                                        )}

                                        <div className="mt-4 border-t border-gray-100 pt-4">
                                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                Smart Kitchen &amp; program highlights
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {scBadges.length > 0 ? (
                                                    scBadges.map((b) => <NutrientBadge key={b} type={b} />)
                                                ) : (
                                                    <p className="font-body text-sm text-[#555555]">—</p>
                                                )}
                                            </div>
                                        </div>

                                        {nutritionResult?.categoryWarnings?.length ? (
                                            <div className="mt-4 rounded-[12px] border border-amber-200 bg-amber-50 p-3">
                                                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-amber-900">
                                                    Warnings
                                                </p>
                                                <ul className="mt-2 list-inside list-disc space-y-1 font-body text-sm text-amber-950">
                                                    {nutritionResult?.categoryWarnings?.map((w) => (
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

function MealLibraryPage(props) {
    const page = usePage();
    const flashSuccess = typeof page.props.flash?.success === 'string' ? page.props.flash.success : null;
    const flashError = typeof page.props.flash?.error === 'string' ? page.props.flash.error : null;
    const mealLibrarySchemaNotice =
        typeof page.props.mealLibrarySchemaNotice === 'string' ? page.props.mealLibrarySchemaNotice : null;
    return (
        <MealLibraryPageContent
            {...props}
            flashSuccess={flashSuccess}
            flashError={flashError}
            mealLibrarySchemaNotice={mealLibrarySchemaNotice}
        />
    );
}

MealLibraryPage.layout = (page) => <AdminInertiaShell>{page}</AdminInertiaShell>;

export default MealLibraryPage;
