import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import AdminInertiaShell from '../../Layouts/AdminInertiaShell.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import MicronutrientInput from '../../Components/Atoms/TextInput/MicronutrientInput.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import NutrientBadge from '../../Components/Atoms/MealSystem/NutrientBadge.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';

/** Ingredient taxonomy for the create-ingredient dropdown (Storybook: `DropdownTextInput`). */
const INGREDIENT_CATEGORY_OPTIONS = [
    'Proteins',
    'Vegetables',
    'Grains',
    'Fats',
    'Base Recipe',
    'Other',
];

/** Mirrors `App\Enums\DietTag::toDropdownOptions()` for Storybook / non-Inertia renders. */
const DEFAULT_DIET_TAGS = [
    { value: 'balanced', label: 'Balanced' },
    { value: 'ketogenic', label: 'Keto' },
    { value: 'intermittent_fasting', label: 'Intermittent fasting' },
];

const PAGE_SIZE = 50;

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function coerceNumber(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }
    const n = Number(value);
    return Number.isNaN(n) ? 0 : n;
}

/**
 * Prefer flattened API fields; fall back to micronutrients JSON keys (e.g. vitamin_c, zinc).
 *
 * @param {Record<string, unknown>} raw
 * @returns {LibraryIngredientRow}
 */
function normalizeLibraryRow(raw) {
    const m = isPlainObject(raw.micronutrients) ? raw.micronutrients : {};
    const pick = (flatKey, jsonKey) => coerceNumber(raw[flatKey] ?? m[jsonKey]);

    return {
        id: String(raw.id ?? ''),
        name: String(raw.name ?? ''),
        category: String(raw.category ?? raw.usda_food_category ?? '').trim(),
        fdc: String(raw.fdc ?? '—'),
        highlights: Array.isArray(raw.highlights) ? raw.highlights : [],
        calories: coerceNumber(raw.calories),
        protein: coerceNumber(raw.protein),
        carbs: coerceNumber(raw.carbs),
        fat: coerceNumber(raw.fat),
        vitA: pick('vitA', 'vitamin_a'),
        vitB6: pick('vitB6', 'vitamin_b6'),
        vitB9: pick('vitB9', 'vitamin_b9'),
        vitB12: pick('vitB12', 'vitamin_b12'),
        vitC: pick('vitC', 'vitamin_c'),
        vitD: pick('vitD', 'vitamin_d'),
        vitE: pick('vitE', 'vitamin_e'),
        vitK: pick('vitK', 'vitamin_k'),
        calcium: pick('calcium', 'calcium'),
        iron: pick('iron', 'iron'),
        magnesium: pick('magnesium', 'magnesium'),
        potassium: pick('potassium', 'potassium'),
        zinc: pick('zinc', 'zinc'),
        sodium: pick('sodium', 'sodium'),
        sugar: pick('sugar', 'sugar'),
        fiber: pick('fiber', 'fiber'),
    };
}
const ROW_HOVER = 'hover:bg-[#F8F9F6]';
const PAGE_BG = 'bg-[#F8F9F6]';

const VITAMIN_COLS = [
    { key: 'vitA', label: 'Vit A' },
    { key: 'vitB6', label: 'Vit B6' },
    { key: 'vitB9', label: 'Vit B9' },
    { key: 'vitB12', label: 'Vit B12' },
    { key: 'vitC', label: 'Vit C' },
    { key: 'vitD', label: 'Vit D' },
    { key: 'vitE', label: 'Vit E' },
    { key: 'vitK', label: 'Vit K' },
];

const MINERAL_MACRO_COLS = [
    { key: 'calcium', label: 'Calcium' },
    { key: 'iron', label: 'Iron' },
    { key: 'magnesium', label: 'Magnesium' },
    { key: 'potassium', label: 'Potassium' },
    { key: 'zinc', label: 'Zinc' },
    { key: 'sodium', label: 'Sodium' },
    { key: 'sugar', label: 'Sugar' },
    { key: 'fiber', label: 'Fiber' },
];

function formatNumber(value) {
    if (value === null || value === undefined) {
        return '—';
    }
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    if (Math.abs(n) >= 100) {
        return String(Math.round(n));
    }
    return n % 1 === 0 ? String(n) : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

/** @param {{ value: string; label: string }[]} items */
function dietTagDropdownOptions(items) {
    return ['', ...items.map((item) => item.label)];
}

/**
 * Presentational ingredients library (no Inertia). Use in Storybook; in the app, prefer {@link IngredientsLibraryPageContent}.
 *
 * @param {{
 *   dietTags?: { value: string; label: string }[];
 *   ingredients?: LibraryIngredientRow[];
 *   csvTemplateUrl?: string;
 *   csvExportUrl?: string;
 *   csvImportUrl?: string;
 *   flashSuccess?: string | null;
 * }} props
 */

/** @typedef {{ id: string; name: string; category: string; fdc: string; highlights: string[]; calories: number; protein: number; carbs: number; fat: number; vitA: number; vitB6: number; vitB9: number; vitB12: number; vitC: number; vitD: number; vitE: number; vitK: number; calcium: number; iron: number; magnesium: number; potassium: number; zinc: number; sodium: number; sugar: number; fiber: number }} LibraryIngredientRow — `category` is the USDA food category string from the API. */

export function IngredientsLibraryPageView({
    dietTags = DEFAULT_DIET_TAGS,
    ingredients = [],
    csvTemplateUrl = '#',
    csvExportUrl = '#',
    csvImportUrl = '#',
    flashSuccess = null,
}) {
    const [query, setQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const searchRootRef = useRef(null);
    const [searchMenuRect, setSearchMenuRect] = useState(/** @type {{ left: number; top: number; width: number } | null} */ (null));
    /** Selected row ids (table-driven state for Delete Selected button). */
    const [selectedRows, setSelectedRows] = useState(/** @type {string[]} */ ([]));
    const [createOpen, setCreateOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    /** Create-ingredient drawer (controlled; reset each time drawer opens). */
    const [createCategory, setCreateCategory] = useState('');
    const [createName, setCreateName] = useState('');
    const [createCalories, setCreateCalories] = useState('');
    const [createProtein, setCreateProtein] = useState('');
    const [createCarbs, setCreateCarbs] = useState('');
    const [createFat, setCreateFat] = useState('');
    const [createMicronutrients, setCreateMicronutrients] = useState('');
    const [createDietTagLabel, setCreateDietTagLabel] = useState('');

    const dietTagOptions = useMemo(() => dietTagDropdownOptions(dietTags), [dietTags]);

    const libraryRows = useMemo(() => {
        const list = Array.isArray(ingredients) ? ingredients : [];
        return list.map((raw) => normalizeLibraryRow(raw));
    }, [ingredients]);

    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);

    useEffect(() => {
        setVisibleCount(PAGE_SIZE);
    }, [query, ingredients]);

    useEffect(() => {
        if (!createOpen) {
            return;
        }
        setCreateCategory('');
        setCreateName('');
        setCreateCalories('');
        setCreateProtein('');
        setCreateCarbs('');
        setCreateFat('');
        setCreateMicronutrients('');
        setCreateDietTagLabel('');
    }, [createOpen]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            const root = searchRootRef.current;
            if (!root) {
                return;
            }
            const t = event.target;
            if (!(t instanceof Node)) {
                return;
            }
            if (!root.contains(t) && !t.closest('[data-ingredients-library-search-suggest]')) {
                setSearchOpen(false);
            }
        };
        document.addEventListener('mousedown', onDocMouseDown);
        return () => document.removeEventListener('mousedown', onDocMouseDown);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }
        if (!searchOpen) {
            setSearchMenuRect(null);
            return undefined;
        }

        const updateRect = () => {
            const el = document.getElementById('ingredients-library-search');
            if (!el) {
                return;
            }
            const r = el.getBoundingClientRect();
            setSearchMenuRect({ left: r.left, top: r.bottom, width: r.width });
        };

        updateRect();
        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);
        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [searchOpen]);

    const filteredRows = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return libraryRows;
        }
        return libraryRows.filter((r) => {
            const nameMatch = r.name.toLowerCase().includes(q);
            const categoryMatch = String(r.category ?? '').toLowerCase().includes(q);
            const highlightMatch =
                Array.isArray(r.highlights) &&
                r.highlights.some((h) => String(h).toLowerCase().includes(q));
            return nameMatch || categoryMatch || highlightMatch;
        });
    }, [query, libraryRows]);

    const displayedRows = useMemo(
        () => filteredRows.slice(0, Math.min(visibleCount, filteredRows.length)),
        [filteredRows, visibleCount],
    );

    const searchMatches = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q.length < 1) {
            return [];
        }
        return libraryRows
            .filter(
                (r) =>
                    r.name.toLowerCase().includes(q) ||
                    String(r.category ?? '')
                        .toLowerCase()
                        .includes(q) ||
                    (Array.isArray(r.highlights) &&
                        r.highlights.some((h) => String(h).toLowerCase().includes(q))),
            )
            .slice(0, 10);
    }, [query, libraryRows]);

    const selectedRowSet = useMemo(() => new Set(selectedRows), [selectedRows]);
    const allVisibleSelected =
        displayedRows.length > 0 && displayedRows.every((r) => selectedRowSet.has(r.id));
    const anySelected = selectedRows.length > 0;

    function toggleAllVisible() {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (allVisibleSelected) {
                displayedRows.forEach((r) => next.delete(r.id));
            } else {
                displayedRows.forEach((r) => next.add(r.id));
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

                <section
                    className="relative z-0 rounded-[12px] border border-gray-200 bg-white shadow-sm"
                    aria-labelledby="ingredients-library-heading"
                >
                    <h2 id="ingredients-library-heading" className="sr-only">
                        Ingredients library
                    </h2>
                    <p id="ingredients-library-desc" className="sr-only">
                        Search, audit, and export ingredients for Smart Kitchen workflows.
                    </p>

                    <div
                        className="flex w-full flex-col gap-6 rounded-t-[12px] border-b border-gray-200 px-5 pb-6 pt-6"
                        aria-describedby="ingredients-library-desc"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-3">
                            <div className="shrink-0">
                                <Button
                                    label="Create ingredient"
                                    variant="primary"
                                    className="shrink-0 uppercase tracking-wide"
                                    onClick={() => setCreateOpen(true)}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <CSVUploader
                                    className="w-full pt-0"
                                    importCsvTemplateUrl={csvTemplateUrl}
                                    exportUrl={csvExportUrl}
                                    onUpload={(file) => {
                                        router.post(
                                            csvImportUrl,
                                            { file },
                                            { forceFormData: true, preserveScroll: true },
                                        );
                                    }}
                                />
                            </div>
                        </div>

                        <div className="w-full min-w-0">
                            <div ref={searchRootRef} className="relative">
                                <TextInput
                                    id="ingredients-library-search"
                                    label="Search ingredients"
                                    placeholder="Search by name, USDA category, or Smart Kitchen highlights…"
                                    value={query}
                                    onChange={(e) => {
                                        const v = e.target.value;
                                        setQuery(v);
                                        setSearchOpen(true);
                                    }}
                                    onFocus={() => setSearchOpen(true)}
                                    className="!max-w-none"
                                />

                                {searchOpen && searchMatches.length > 0 && searchMenuRect
                                    ? createPortal(
                                          <div
                                              data-ingredients-library-search-suggest
                                              className="fixed z-[9999]"
                                              style={{
                                                  left: `${searchMenuRect.left}px`,
                                                  top: `${searchMenuRect.top + 8}px`,
                                                  width: `${searchMenuRect.width}px`,
                                              }}
                                          >
                                              <div className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl">
                                                  <div className="max-h-56 overflow-auto">
                                                      {searchMatches.map((m) => (
                                                          <button
                                                              key={m.id}
                                                              type="button"
                                                              className="flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors hover:bg-[#F8F9F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset"
                                                              onClick={() => {
                                                                  setQuery(m.name);
                                                                  setSearchOpen(false);
                                                              }}
                                                          >
                                                              <span className="min-w-0 truncate">{m.name}</span>
                                                              <span className="shrink-0 text-xs font-medium text-[#555555]">
                                                                  {m.category}
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
                        </div>

                        <div className="-mx-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 pt-4">
                            <div className="min-w-0">
                                <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">Ingredients</p>
                                <p className="mt-0.5 font-body text-xs text-[#555555]">
                                    <span className="font-semibold text-[#374151]">{libraryRows.length}</span> in library
                                    {' · '}
                                    {displayedRows.length} of {filteredRows.length} in table
                                    {filteredRows.length < libraryRows.length ? ' (filtered)' : ''}
                                    {' · '}
                                    {selectedRows.length} selected
                                    {filteredRows.length > PAGE_SIZE && displayedRows.length < filteredRows.length
                                        ? ' · use "Load more" below for additional rows'
                                        : ''}
                                </p>
                            </div>

                            <Button
                                label="Delete selected"
                                variant="ghost"
                                disabled={selectedRows.length === 0}
                                onClick={() => {
                                    if (!anySelected) {
                                        return;
                                    }
                                    setConfirmOpen(true);
                                }}
                                className={[
                                    // Ghost variant utilities (bg-transparent, text color) can win in build order —
                                    // use ! overrides so solid red appears as soon as a row is selected (not only on hover).
                                    // Disabled atom adds opacity-50; destructive ghost/disabled wants full-opacity tint instead.
                                    'h-[44px] min-h-[44px] rounded-[12px] px-5 text-[13px] transition-colors duration-200',
                                    'disabled:!cursor-not-allowed disabled:!opacity-100',
                                    anySelected
                                        ? '!bg-[#C44F5D] !text-white hover:!bg-[#B14552] hover:!text-white disabled:!opacity-100'
                                        : '!bg-[#C44F5D]/20 !text-[#8A8A8A] hover:!bg-[#C44F5D]/20 hover:!text-[#8A8A8A]',
                                ].join(' ')}
                            />
                        </div>
                    </div>

                    <div>
                        <div className="overflow-x-auto">
                    <table className="min-w-[1400px] w-full border-collapse text-[#1F2937]">
                        <thead className="bg-white">
                            <tr className="border-b border-gray-200">
                                <th className="sticky left-0 z-20 w-[54px] bg-white px-4 py-3 text-left">
                                    <button
                                        type="button"
                                        onClick={toggleAllVisible}
                                        aria-label={allVisibleSelected ? 'Deselect all visible' : 'Select all visible'}
                                        className="inline-flex items-center"
                                    >
                                        <SquareCheckbox checked={allVisibleSelected} />
                                    </button>
                                </th>
                                <th className="sticky left-[54px] z-20 w-[320px] bg-white px-4 py-3 text-left">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        Ingredient
                                    </span>
                                </th>
                                <th className="w-[170px] px-4 py-3 text-left">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        Category
                                    </span>
                                </th>
                                <th className="w-[120px] px-4 py-3 text-left">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        FDC
                                    </span>
                                </th>
                                <th className="w-[220px] px-4 py-3 text-left">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        SC highlights
                                    </span>
                                </th>
                                <th className="w-[110px] px-4 py-3 text-right">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        Calories
                                    </span>
                                </th>
                                <th className="w-[110px] px-4 py-3 text-right">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        Protein
                                    </span>
                                </th>
                                <th className="w-[110px] px-4 py-3 text-right">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        Carbs
                                    </span>
                                </th>
                                <th className="w-[110px] px-4 py-3 text-right">
                                    <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                        Fat
                                    </span>
                                </th>

                                {VITAMIN_COLS.map((c, i) => (
                                    <th
                                        key={c.key}
                                        className={[
                                            'w-[110px] px-4 py-3 text-right',
                                            i === VITAMIN_COLS.length - 1 ? 'border-r border-gray-200' : '',
                                        ].join(' ')}
                                    >
                                        <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                            {c.label}
                                        </span>
                                    </th>
                                ))}

                                {MINERAL_MACRO_COLS.map((c) => (
                                    <th key={c.key} className="w-[120px] px-4 py-3 text-right">
                                        <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                            {c.label}
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {displayedRows.map((r) => {
                                const checked = selectedRowSet.has(r.id);
                                return (
                                    <tr key={r.id} className={`border-b border-gray-100 ${ROW_HOVER}`}>
                                        <td className="sticky left-0 z-10 bg-white px-4 py-3">
                                            <button
                                                type="button"
                                                onClick={() => toggleRow(r.id)}
                                                aria-label={checked ? `Deselect ${r.name}` : `Select ${r.name}`}
                                                className="inline-flex items-center"
                                            >
                                                <SquareCheckbox checked={checked} />
                                            </button>
                                        </td>
                                        <td className="sticky left-[54px] z-10 bg-white px-4 py-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-body text-sm font-semibold text-[#1F2937]">
                                                    {r.name}
                                                </p>
                                                <p className="mt-0.5 font-body text-xs text-[#555555]">
                                                    ID: {r.id}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-body text-sm text-[#1F2937]">{r.category}</td>
                                        <td className="px-4 py-3 font-body text-sm text-[#1F2937]">{r.fdc}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                {(r.highlights ?? []).map((h) => (
                                                    <NutrientBadge key={h} type={h} />
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-body text-sm text-[#1F2937]">
                                            {formatNumber(r.calories)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-body text-sm text-[#1F2937]">
                                            {formatNumber(r.protein)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-body text-sm text-[#1F2937]">
                                            {formatNumber(r.carbs)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-body text-sm text-[#1F2937]">
                                            {formatNumber(r.fat)}
                                        </td>

                                        {VITAMIN_COLS.map((c, i) => (
                                            <td
                                                key={c.key}
                                                className={[
                                                    'px-4 py-3 text-right font-body text-sm text-[#1F2937]',
                                                    i === VITAMIN_COLS.length - 1 ? 'border-r border-gray-200' : '',
                                                ].join(' ')}
                                            >
                                                {formatNumber(r[c.key])}
                                            </td>
                                        ))}

                                        {MINERAL_MACRO_COLS.map((c) => (
                                            <td
                                                key={c.key}
                                                className="px-4 py-3 text-right font-body text-sm text-[#1F2937]"
                                            >
                                                {formatNumber(r[c.key])}
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                        </div>
                {filteredRows.length > visibleCount ? (
                    <div className="flex justify-center border-t border-gray-200 px-5 py-4">
                        <Button
                            label={`Load more (${Math.min(PAGE_SIZE, filteredRows.length - visibleCount)} rows)`}
                            variant="secondary"
                            type="button"
                            onClick={() => setVisibleCount((c) => c + PAGE_SIZE)}
                        />
                    </div>
                ) : null}
                    </div>
                </section>

            {createOpen ? (
                <div className="fixed inset-0 z-[100]">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setCreateOpen(false)}
                        aria-label="Close create ingredient drawer"
                    />
                    <div className="absolute right-0 top-0 h-full w-full max-w-[520px] bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                            <div>
                                <p className="font-montserrat text-sm font-bold uppercase tracking-[0.14em] text-[#555555]">
                                    Create ingredient
                                </p>
                                <p className="mt-0.5 font-montserrat text-[18px] font-bold tracking-tight text-[#262A22]">
                                    New ingredient
                                </p>
                            </div>
                            <Button label="Close" variant="ghost" onClick={() => setCreateOpen(false)} />
                        </div>
                        <div className="space-y-4 p-6">
                            <DropdownTextInput
                                label="Ingredient Category"
                                value={createCategory}
                                options={INGREDIENT_CATEGORY_OPTIONS}
                                onChange={setCreateCategory}
                                listboxAriaLabel="Ingredient Category"
                                className="!max-w-none"
                            />
                            <DropdownTextInput
                                label="Diet tag (optional)"
                                value={createDietTagLabel}
                                options={dietTagOptions}
                                onChange={setCreateDietTagLabel}
                                listboxAriaLabel="Diet tag"
                                className="!max-w-none"
                            />
                            <TextInput
                                label="Name"
                                placeholder="e.g. Chicken breast"
                                value={createName}
                                onChange={(e) => setCreateName(e.target.value)}
                                className="!max-w-none"
                            />
                            <div className="grid grid-cols-2 gap-4">
                                <TextInput
                                    label="Calories"
                                    placeholder="0"
                                    value={createCalories}
                                    onChange={(e) => setCreateCalories(e.target.value)}
                                    className="!max-w-none"
                                />
                                <TextInput
                                    label="Protein"
                                    placeholder="0"
                                    value={createProtein}
                                    onChange={(e) => setCreateProtein(e.target.value)}
                                    className="!max-w-none"
                                />
                                <TextInput
                                    label="Carbs"
                                    placeholder="0"
                                    value={createCarbs}
                                    onChange={(e) => setCreateCarbs(e.target.value)}
                                    className="!max-w-none"
                                />
                                <TextInput
                                    label="Fat"
                                    placeholder="0"
                                    value={createFat}
                                    onChange={(e) => setCreateFat(e.target.value)}
                                    className="!max-w-none"
                                />
                            </div>
                            <MicronutrientInput
                                label="Micronutrients"
                                value={createMicronutrients}
                                onChange={(e) => setCreateMicronutrients(e.target.value)}
                                placeholder="Example: B12: 2.4 mcg, Folate: 400 mcg, Iron: 18 mg, Magnesium: 400 mg"
                                className="!max-w-none"
                            />
                            <div className="pt-2">
                                <Button
                                    label="Add ingredient"
                                    variant="primary"
                                    type="button"
                                    onClick={() => setCreateOpen(false)}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}

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
                        aria-label="Delete selected ingredients"
                        className="relative w-full max-w-[480px] rounded-[12px] bg-white p-8 shadow-2xl"
                    >
                        <p className="text-center font-montserrat text-xs font-bold uppercase tracking-[0.22em] text-[#C44F5D]">
                            Warning
                        </p>
                        <p className="mt-3 text-center font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]">
                            Delete selected ingredients permanently?
                        </p>
                        <p className="mt-3 text-center font-body text-sm text-[#555555]">
                            This action can’t be undone. You can also export a CSV first.
                        </p>

                        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Button
                                label="Cancel"
                                variant="secondary"
                                onClick={() => setConfirmOpen(false)}
                                className="w-full"
                            />
                            <Button
                                label="Delete permanently"
                                variant="ghost"
                                className={
                                    // Same as Delete selected: ghost bg/text utilities can beat plain overrides in CSS order.
                                    'w-full rounded-[12px] transition-colors duration-200 ' +
                                    '!bg-[#C44F5D] !text-white hover:!bg-[#B14552] hover:!text-white'
                                }
                                onClick={() => {
                                    setSelectedRows([]);
                                    setConfirmOpen(false);
                                }}
                            />
                        </div>
                    </div>
                </div>
            ) : null}
            </div>
        </div>
    );
}

/** Inertia-connected page body; wraps {@link IngredientsLibraryPageView} with `usePage` flash. */
export function IngredientsLibraryPageContent(props) {
    const { flash } = usePage().props;
    const flashSuccess = typeof flash?.success === 'string' ? flash.success : null;
    return <IngredientsLibraryPageView {...props} flashSuccess={flashSuccess} />;
}

function IngredientsLibraryPage(props) {
    return <IngredientsLibraryPageContent {...props} />;
}

IngredientsLibraryPage.layout = (page) => <AdminInertiaShell>{page}</AdminInertiaShell>;

export default IngredientsLibraryPage;
