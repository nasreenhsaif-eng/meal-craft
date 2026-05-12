import { createPortal } from 'react-dom';
import { useEffect, useMemo, useRef, useState } from 'react';
import AdminInertiaShell from '../../Layouts/AdminInertiaShell.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import MealPlanCard from '../../Components/MealPlanCard.jsx';
import DietaryTagsMultiSelect from '../../Components/MealSystem/DietaryTagsMultiSelect.jsx';
import { MEAL_PLAN_TAG_OPTIONS } from '../../meal-library/mealTaxonomy.js';

const PAGE_BG = 'bg-[#F8F9F6]';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const DEFAULT_DIET_TYPES = [
    { value: 'balanced', label: 'Balanced' },
    { value: 'keto', label: 'Keto' },
    { value: 'intermittent_fasting', label: 'Intermittent fasting' },
];

const DEFAULT_CYCLE_PHASES = [
    { value: 'menstrual', label: 'Menstrual' },
    { value: 'follicular', label: 'Follicular' },
    { value: 'ovulatory', label: 'Ovulatory' },
    { value: 'luteal', label: 'Luteal' },
];

const MOCK_MEALS = [
    {
        id: 'm-1',
        name: 'Post-workout salmon bowl',
        imageUrl: 'https://images.unsplash.com/photo-1553621042-f6e147245754?auto=format&fit=crop&w=900&q=80',
        nutrition: {
            calories: 520,
            protein: 44,
            carbs: 38,
            fat: 22,
            b9_folate: 180,
            b12: 2.2,
            iron: 4.5,
            magnesium: 140,
            zinc: 3.2,
        },
    },
    {
        id: 'm-2',
        name: 'Iron boost lentil stew',
        imageUrl: 'https://images.unsplash.com/photo-1604908554238-3f9b9b0c4b6c?auto=format&fit=crop&w=900&q=80',
        nutrition: {
            calories: 430,
            protein: 26,
            carbs: 62,
            fat: 10,
            b9_folate: 240,
            b12: 0.4,
            iron: 7.2,
            magnesium: 165,
            zinc: 2.8,
        },
    },
    {
        id: 'm-3',
        name: 'Keto chicken + greens',
        imageUrl: 'https://images.unsplash.com/photo-1543353071-087092ec393a?auto=format&fit=crop&w=900&q=80',
        nutrition: {
            calories: 610,
            protein: 52,
            carbs: 14,
            fat: 38,
            b9_folate: 110,
            b12: 1.8,
            iron: 5.1,
            magnesium: 120,
            zinc: 4.0,
        },
    },
];

const MOCK_PLANS = [
    {
        id: 'p-1',
        name: 'Sickle Cell Support — Level 1',
        category: 'Clinical',
        imageUrl: 'https://images.unsplash.com/photo-1543364195-bfe6e4932397?auto=format&fit=crop&w=1200&q=80',
        tags: ['Sickle Cell Anemia', 'Balanced'],
        dailyMacros: { calories: 2050, protein: 125, carbs: 245, fat: 70 },
    },
    {
        id: 'p-2',
        name: 'Hormone Feast — 7 Day Reset',
        category: 'Wellness',
        imageUrl: 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=1200&q=80',
        tags: ['Hormone Feast', 'Balanced'],
        dailyMacros: { calories: 1850, protein: 110, carbs: 220, fat: 60 },
    },
];

function sumNutrition(rows) {
    const out = {
        calories: 0,
        protein: 0,
        carbs: 0,
        fat: 0,
        b9_folate: 0,
        b12: 0,
        iron: 0,
        magnesium: 0,
        zinc: 0,
    };
    rows.forEach((m) => {
        const n = m?.nutrition ?? {};
        Object.keys(out).forEach((k) => {
            const v = n[k];
            out[k] += typeof v === 'number' && Number.isFinite(v) ? v : 0;
        });
    });
    return out;
}

/**
 * @param {{
 *   dietTypes?: { value: string; label: string }[];
 *   cyclePhases?: { value: string; label: string }[];
 * }} props
 */
export function MealPlanLibraryPageContent({
    dietTypes = DEFAULT_DIET_TYPES,
    cyclePhases = DEFAULT_CYCLE_PHASES,
}) {
    const [query, setQuery] = useState('');
    const [createOpen, setCreateOpen] = useState(false);

    // Search combobox (library header)
    const [searchOpen, setSearchOpen] = useState(false);
    const searchRootRef = useRef(null);
    const [searchMenuRect, setSearchMenuRect] = useState(null);

    // Create modal form state
    const [formName, setFormName] = useState('');
    const [formTags, setFormTags] = useState(/** @type {string[]} */ ([]));
    const [formDescription, setFormDescription] = useState('');
    const [targets, setTargets] = useState({ calories: '', protein: '', carbs: '', fat: '' });

    const [activeDay, setActiveDay] = useState(1);

    const [slotsById, setSlotsById] = useState(
        /** @type {Record<string, { mealQuery: string; mealId: string }>} */ ({
            // Day 1 defaults
            'd1-breakfast-1': { mealQuery: '', mealId: '' },
            'd1-breakfast-2': { mealQuery: '', mealId: '' },
            'd1-meal-1': { mealQuery: '', mealId: '' },
            'd1-meal-2': { mealQuery: '', mealId: '' },
            'd1-meal-3': { mealQuery: '', mealId: '' },
            'd1-meal-4': { mealQuery: '', mealId: '' },
            'd1-sidesalad-1': { mealQuery: '', mealId: '' },
            'd1-soup-1': { mealQuery: '', mealId: '' },
            'd1-dessert-1': { mealQuery: '', mealId: '' },
            'd1-dessert-2': { mealQuery: '', mealId: '' },
        }),
    );

    const [activeSlotId, setActiveSlotId] = useState(/** @type {string|null} */ (null));
    const mealSuggestRootRef = useRef(null);
    const [mealMenuRect, setMealMenuRect] = useState(null);

    useEffect(() => {
        if (!createOpen) {
            setActiveSlotId(null);
        }
    }, [createOpen]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            const t = event.target;
            if (!(t instanceof Node)) {
                return;
            }
            const root = searchRootRef.current;
            if (root && !root.contains(t) && !t.closest('[data-meal-plan-library-search-suggest]')) {
                setSearchOpen(false);
            }
            const mealRoot = mealSuggestRootRef.current;
            if (mealRoot && !mealRoot.contains(t) && !t.closest('[data-meal-plan-library-meal-suggest]')) {
                setActiveSlotId(null);
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
            const el = document.getElementById('meal-plan-library-search');
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

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }
        if (activeSlotId === null) {
            setMealMenuRect(null);
            return undefined;
        }
        const updateRect = () => {
            const el = document.getElementById(`meal-slot-combobox-${activeSlotId}`);
            if (!el) {
                return;
            }
            const r = el.getBoundingClientRect();
            setMealMenuRect({ left: r.left, top: r.bottom, width: r.width });
        };
        updateRect();
        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);
        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [activeSlotId]);

    const filteredPlans = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return MOCK_PLANS;
        }
        return MOCK_PLANS.filter((p) => {
            const nameMatch = p.name.toLowerCase().includes(q);
            const categoryMatch = String(p.category ?? '')
                .toLowerCase()
                .includes(q);
            const tagMatch = Array.isArray(p.tags) && p.tags.some((t) => String(t).toLowerCase().includes(q));
            return nameMatch || categoryMatch || tagMatch;
        });
    }, [query]);

    const searchMatches = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q.length < 1) return [];
        return MOCK_PLANS.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                String(p.category ?? '')
                    .toLowerCase()
                    .includes(q) ||
                p.tags.some((t) => String(t).toLowerCase().includes(q)),
        ).slice(0, 10);
    }, [query]);

    const SLOT_SECTIONS = useMemo(() => {
        return [
            { key: 'breakfast', label: 'Breakfasts', count: 2 },
            { key: 'meal', label: 'Meal choices', count: 4 },
            { key: 'sidesalad', label: 'Side salad', count: 2 },
            { key: 'soup', label: 'Soup of the day', count: 1 },
            { key: 'dessert', label: 'Desserts', count: 2 },
        ];
    }, []);

    function slotKey(day, sectionKey, idx) {
        return `d${day}-${sectionKey}-${idx}`;
    }

    const mealById = useMemo(() => new Map(MOCK_MEALS.map((m) => [m.id, m])), []);

    return (
        <div className={`min-h-screen ${PAGE_BG} px-4 pb-8 pt-4 font-sans md:px-8`}>
            <div className="mx-auto max-w-[1400px] space-y-6">
                <section className="relative z-0 rounded-[12px] border border-gray-200 bg-white shadow-sm" aria-labelledby="meal-plan-library-heading">
                    <h2 id="meal-plan-library-heading" className="sr-only">
                        Meal Plan library
                    </h2>
                    <p id="meal-plan-library-desc" className="sr-only">
                        Orchestrate protocols and daily averages across your meal system. Diet type options:{' '}
                        {dietTypes.map((d) => `${d.label} (${d.value})`).join(', ')}. Cycle phase options:{' '}
                        {cyclePhases.map((p) => `${p.label} (${p.value})`).join(', ')}.
                    </p>

                    <div
                        className="flex w-full flex-col gap-6 rounded-t-[12px] border-b border-gray-200 px-5 pb-6 pt-6"
                        aria-describedby="meal-plan-library-desc"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-3">
                            <div className="shrink-0">
                                <Button
                                    label="Create meal plan"
                                    variant="primary"
                                    type="button"
                                    className="shrink-0 uppercase tracking-wide"
                                    onClick={() => setCreateOpen(true)}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <CSVUploader
                                    className="w-full pt-0"
                                    templateUrl="#"
                                    exportUrl="#"
                                    onUpload={async (file) => {
                                        void file;
                                    }}
                                />
                            </div>
                        </div>

                        <div className="w-full min-w-0">
                            <div ref={searchRootRef} className="relative">
                                <TextInput
                                    id="meal-plan-library-search"
                                    label="Search meal plans"
                                    placeholder="Search by name, category, or tag…"
                                    value={query}
                                    onChange={(e) => {
                                        setQuery(e.target.value);
                                        setSearchOpen(true);
                                    }}
                                    onFocus={() => setSearchOpen(true)}
                                    className="!max-w-none"
                                />

                                {searchOpen && searchMatches.length > 0 && searchMenuRect
                                    ? createPortal(
                                          <div
                                              data-meal-plan-library-search-suggest
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
                    </div>

                    <div className="p-5">
                        {filteredPlans.length === 0 ? (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] p-8 text-center font-body text-sm text-[#555555]">
                                No meal plans match your search. Try another name, category, or tag.
                            </p>
                        ) : (
                            <ul className="m-0 grid list-none grid-cols-1 justify-items-center gap-8 p-0 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                {filteredPlans.map((p) => (
                                    <li key={p.id} className="w-full max-w-[310px]">
                                        <MealPlanCard
                                            title={p.name}
                                            imageUrl={p.imageUrl}
                                            dailyMacros={p.dailyMacros}
                                            tags={p.tags}
                                            onPrimaryAction={() => {}}
                                            primaryActionLabel="View details"
                                            className="transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-xl active:translate-y-0 active:scale-[0.98] active:shadow-md"
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>

            {createOpen ? (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setCreateOpen(false)}
                        aria-label="Close create meal plan modal"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Create new meal plan"
                        className="relative w-full max-w-[1200px] overflow-hidden rounded-[12px] bg-[#F8F9F6] shadow-2xl"
                    >
                        <div className="absolute right-3 top-3 z-20">
                            <Button label="Close" variant="ghost" onClick={() => setCreateOpen(false)} />
                        </div>

                        <div className="flex max-h-[85vh] flex-col overflow-hidden">
                            <div className="flex-1 overflow-y-auto p-10">
                                <div className="rounded-[12px] border border-gray-200 bg-white p-6">
                                    <h2 className="font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]">
                                        Create New Meal Plan
                                    </h2>
                                    <p className="mt-1 font-body text-sm text-[#555555]">
                                        Define protocol strategy, targets, and schedule meals across 7 days.
                                    </p>

                                    <div className="mt-6 space-y-6">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <TextInput
                                                label="Meal Plan Name"
                                                placeholder="e.g. Sickle Cell Support — Level 1"
                                                value={formName}
                                                onChange={(e) => setFormName(e.target.value)}
                                                className="!max-w-none"
                                            />
                                            <div className="block w-full text-left">
                                                <p className="mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                                    Meal Plan Tag
                                                </p>
                                                <DietaryTagsMultiSelect
                                                    options={MEAL_PLAN_TAG_OPTIONS}
                                                    value={formTags}
                                                    onChange={setFormTags}
                                                />
                                            </div>
                                        </div>

                                        <div className="block w-full text-left">
                                            <label className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                                Plan description &amp; goal
                                            </label>
                                            <textarea
                                                value={formDescription}
                                                onChange={(e) => setFormDescription(e.target.value)}
                                                rows={3}
                                                className="block w-full resize-y rounded-[12px] border border-[#E5E7EB] bg-white px-4 py-3 font-body text-[15px] text-[#1F2937] shadow-sm outline-none focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                                placeholder="Clinical or wellness objectives…"
                                            />
                                        </div>

                                        <div className="rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-4">
                                            <p className="font-montserrat text-sm font-bold text-[#262A22]">Targets</p>
                                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                                <TextInput
                                                    label="Avg Calories"
                                                    placeholder="0"
                                                    value={targets.calories}
                                                    onChange={(e) => setTargets((t) => ({ ...t, calories: e.target.value }))}
                                                    className="!max-w-none"
                                                    inputMode="numeric"
                                                />
                                                <TextInput
                                                    label="Protein"
                                                    placeholder="0"
                                                    value={targets.protein}
                                                    onChange={(e) => setTargets((t) => ({ ...t, protein: e.target.value }))}
                                                    className="!max-w-none"
                                                    inputMode="numeric"
                                                />
                                                <TextInput
                                                    label="Carbs"
                                                    placeholder="0"
                                                    value={targets.carbs}
                                                    onChange={(e) => setTargets((t) => ({ ...t, carbs: e.target.value }))}
                                                    className="!max-w-none"
                                                    inputMode="numeric"
                                                />
                                                <TextInput
                                                    label="Fat"
                                                    placeholder="0"
                                                    value={targets.fat}
                                                    onChange={(e) => setTargets((t) => ({ ...t, fat: e.target.value }))}
                                                    className="!max-w-none"
                                                    inputMode="numeric"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-6 rounded-[12px] border border-gray-200 bg-white p-6">
                                    <div className="flex flex-wrap items-end justify-between gap-4">
                                        <div className="min-w-[220px]">
                                            <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">
                                                Smart Scheduler
                                            </p>
                                            <p className="mt-1 font-body text-sm text-[#555555]">
                                                Select a day and assign meals to each slot.
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {WEEKDAY_LABELS.map((label, i) => {
                                                const day = i + 1; // internal day index (1..7)
                                                const isActive = day === activeDay;
                                                return (
                                                    <PillButton
                                                        key={day}
                                                        label={label}
                                                        variant={isActive ? 'primary' : 'outline'}
                                                        size="sm"
                                                        onClick={() => setActiveDay(day)}
                                                        className={isActive ? '' : 'ring-1 ring-[#E5E7EB]'}
                                                    />
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <div ref={mealSuggestRootRef} className="mt-6 space-y-5">
                                        {SLOT_SECTIONS.map((section) => (
                                            <div key={section.key}>
                                                <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                                    {section.label} ({section.count})
                                                </p>
                                                <div className="space-y-3">
                                                    {Array.from({ length: section.count }).map((_, idx0) => {
                                                        const idx = idx0 + 1;
                                                        const id = slotKey(activeDay, section.key, idx);
                                                        const state = slotsById[id] ?? { mealQuery: '', mealId: '' };
                                                        const q = state.mealQuery.trim().toLowerCase();
                                                        const matches =
                                                            q.length < 1
                                                                ? []
                                                                : MOCK_MEALS.filter((m) => m.name.toLowerCase().includes(q)).slice(0, 10);

                                                        return (
                                                            <div key={id} className="grid gap-3 md:grid-cols-[120px_1fr] md:items-end">
                                                                <div className="rounded-[12px] border border-gray-200 bg-[#F8F9F6] px-4 py-3">
                                                                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                                                        Option
                                                                    </p>
                                                                    <p className="mt-1 font-montserrat text-sm font-bold text-[#262A22]">
                                                                        {idx}
                                                                    </p>
                                                                </div>
                                                                <div className="relative min-w-0">
                                                                    <TextInput
                                                                        id={`meal-slot-combobox-${id}`}
                                                                        label="Meal"
                                                                        placeholder="Type to search…"
                                                                        value={state.mealQuery}
                                                                        onChange={(e) => {
                                                                            const v = e.target.value;
                                                                            setSlotsById((prev) => ({
                                                                                ...prev,
                                                                                [id]: { mealQuery: v, mealId: '' },
                                                                            }));
                                                                            setActiveSlotId(id);
                                                                        }}
                                                                        onFocus={() => setActiveSlotId(id)}
                                                                        className="!max-w-none"
                                                                    />

                                                                    {activeSlotId === id && matches.length > 0 && mealMenuRect
                                                                        ? createPortal(
                                                                              <div
                                                                                  data-meal-plan-library-meal-suggest
                                                                                  className="fixed z-[9999]"
                                                                                  style={{
                                                                                      left: `${mealMenuRect.left}px`,
                                                                                      top: `${mealMenuRect.top + 8}px`,
                                                                                      width: `${mealMenuRect.width}px`,
                                                                                  }}
                                                                              >
                                                                                  <div className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl">
                                                                                      <div className="max-h-56 overflow-auto">
                                                                                          {matches.map((m) => (
                                                                                              <button
                                                                                                  key={m.id}
                                                                                                  type="button"
                                                                                                  className="flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors hover:bg-[#F8F9F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset"
                                                                                                  onClick={() => {
                                                                                                      setSlotsById((prev) => ({
                                                                                                          ...prev,
                                                                                                          [id]: { mealQuery: m.name, mealId: m.id },
                                                                                                      }));
                                                                                                      setActiveSlotId(null);
                                                                                                  }}
                                                                                              >
                                                                                                  <span className="min-w-0 truncate">
                                                                                                      {m.name}
                                                                                                  </span>
                                                                                                  <span className="shrink-0 text-xs font-medium text-[#555555]">
                                                                                                      {Math.round(m.nutrition.calories)} kcal
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
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-gray-200 bg-[#F8F9F6] px-10 pb-8 pt-6">
                                <div className="flex justify-end">
                                    <Button
                                        label="Save meal plan"
                                        variant="primary"
                                        onClick={() => setCreateOpen(false)}
                                        className="justify-center"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function MealPlanLibraryPage(props) {
    return <MealPlanLibraryPageContent {...props} />;
}

MealPlanLibraryPage.layout = (page) => <AdminInertiaShell>{page}</AdminInertiaShell>;

export default MealPlanLibraryPage;
