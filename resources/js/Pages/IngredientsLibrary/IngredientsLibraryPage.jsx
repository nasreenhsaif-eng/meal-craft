import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import MicronutrientInput from '../../Components/Atoms/TextInput/MicronutrientInput.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import SquareCheckbox from '../../Components/Atoms/SquareCheckbox.jsx';
import NutrientBadge from '../../Components/Atoms/MealSystem/NutrientBadge.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';

/** Ingredient taxonomy for the create-ingredient dropdown (Storybook: `DropdownTextInput`). */
const INGREDIENT_CATEGORY_OPTIONS = [
    'Proteins',
    'Vegetables',
    'Grains',
    'Fats',
    'Other',
];

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

const MOCK_INGREDIENTS = [
    {
        id: 'ing-1',
        name: 'Chicken breast, roasted',
        category: 'Proteins',
        fdc: '173686',
        highlights: ['B12', 'Zinc'],
        calories: 165,
        protein: 31,
        carbs: 0,
        fat: 3.6,
        vitA: 0,
        vitB6: 0.6,
        vitB9: 0.01,
        vitB12: 0.3,
        vitC: 0,
        vitD: 0,
        vitE: 0.2,
        vitK: 0,
        calcium: 0.01,
        iron: 0.01,
        magnesium: 0.03,
        potassium: 0.26,
        zinc: 0.01,
        sodium: 0.07,
        sugar: 0,
        fiber: 0,
    },
    {
        id: 'ing-2',
        name: 'Spinach, raw',
        category: 'Vegetables',
        fdc: '168462',
        highlights: ['Folate', 'Iron', 'Magnesium'],
        calories: 23,
        protein: 2.9,
        carbs: 3.6,
        fat: 0.4,
        vitA: 0.47,
        vitB6: 0.19,
        vitB9: 0.19,
        vitB12: 0,
        vitC: 0.03,
        vitD: 0,
        vitE: 0.02,
        vitK: 0.48,
        calcium: 0.1,
        iron: 0.03,
        magnesium: 0.08,
        potassium: 0.56,
        zinc: 0.01,
        sodium: 0.08,
        sugar: 0.4,
        fiber: 2.2,
    },
    {
        id: 'ing-3',
        name: 'Greek yogurt, plain',
        category: 'Other',
        fdc: '170885',
        highlights: ['B12', 'Calcium'],
        calories: 97,
        protein: 9,
        carbs: 3.6,
        fat: 5,
        vitA: 0.06,
        vitB6: 0.05,
        vitB9: 0.01,
        vitB12: 0.7,
        vitC: 0,
        vitD: 0.01,
        vitE: 0.1,
        vitK: 0,
        calcium: 0.11,
        iron: 0,
        magnesium: 0.01,
        potassium: 0.14,
        zinc: 0.01,
        sodium: 0.04,
        sugar: 3.6,
        fiber: 0,
    },
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

export default function IngredientsLibraryPage() {
    const [query, setQuery] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('All categories');
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
            if (!root.contains(event.target)) {
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

    const rows = useMemo(() => {
        const q = query.trim().toLowerCase();
        let list = MOCK_INGREDIENTS;
        if (q) {
            list = list.filter((r) => r.name.toLowerCase().includes(q) || String(r.fdc).includes(q));
        }
        if (categoryFilter !== 'All categories') {
            list = list.filter((r) => r.category === categoryFilter);
        }
        return list;
    }, [query, categoryFilter]);

    const searchMatches = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q.length < 1) {
            return [];
        }
        return MOCK_INGREDIENTS.filter(
            (r) => r.name.toLowerCase().includes(q) || r.highlights.some((h) => String(h).toLowerCase().includes(q)),
        ).slice(0, 10);
    }, [query]);

    const selectedRowSet = useMemo(() => new Set(selectedRows), [selectedRows]);
    const allVisibleSelected = rows.length > 0 && rows.every((r) => selectedRowSet.has(r.id));
    const anySelected = selectedRows.length > 0;

    function toggleAllVisible() {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (allVisibleSelected) {
                rows.forEach((r) => next.delete(r.id));
            } else {
                rows.forEach((r) => next.add(r.id));
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
        <div className={`min-h-screen ${PAGE_BG} px-4 py-8 font-sans md:px-8`}>
            <div className="mx-auto max-w-[1400px] space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-[260px]">
                        <p className="mb-1 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                            Admin / Ingredients Library
                        </p>
                        <h1 className="font-montserrat text-[20px] font-bold tracking-tight text-[#262A22]">
                            Ingredients Library
                        </h1>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Search, audit, and export ingredients for Smart Kitchen workflows.
                        </p>
                    </div>
                    <Button label="Create ingredient" variant="primary" onClick={() => setCreateOpen(true)} />
                </div>

                <section className="rounded-[12px] border border-gray-200 bg-white shadow-sm" aria-labelledby="ingredients-library-heading">
                    <h2 id="ingredients-library-heading" className="sr-only">
                        Ingredients library
                    </h2>

                    <div className="relative z-20 flex flex-col gap-6 border-b border-gray-200 p-5 pt-6">
                        <div className="grid w-full gap-6 sm:grid-cols-[minmax(0,1fr)_280px] sm:items-end sm:gap-8">
                            <div className="w-full min-w-0">
                                <div ref={searchRootRef} className="relative">
                                    <TextInput
                                        id="ingredients-library-search"
                                        label="Search ingredients"
                                        placeholder="Search ingredients by name or keyword..."
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
                                                  className="fixed z-[90]"
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
                            <div className="w-full min-w-0">
                                <DropdownTextInput
                                    label="Ingredient category"
                                    value={categoryFilter}
                                    options={['All categories', ...INGREDIENT_CATEGORY_OPTIONS]}
                                    onChange={setCategoryFilter}
                                    listboxAriaLabel="Filter by ingredient category"
                                    className="!max-w-none"
                                />
                            </div>
                        </div>

                        <CSVUploader
                            className="pt-2"
                            templateUrl="#"
                            exportUrl="#"
                            onUpload={async (file) => {
                                void file;
                            }}
                        />
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                        <div className="min-w-0">
                            <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">Ingredients</p>
                            <p className="mt-0.5 font-body text-xs text-[#555555]">
                                {rows.length} shown • {selectedRows.length} selected
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

                    <div className="relative overflow-x-auto">
                    <table className="min-w-[1400px] w-full border-collapse text-[#1F2937]">
                        <thead className="sticky top-0 z-10 bg-white">
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
                            {rows.map((r) => {
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
                                                {r.highlights.map((h) => (
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
                </section>

            {createOpen ? (
                <div className="fixed inset-0 z-40">
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

