import { createPortal } from 'react-dom';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import { mealPlanLibraryUrls, resolveUrl } from '../../meal-craft/mealCraftPageProps.js';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import MealPlanCard from '../../Components/MealPlanCard.jsx';
import DietaryTagsMultiSelect from '../../Components/MealSystem/DietaryTagsMultiSelect.jsx';
import MealAutocompleteCombobox from '../../Components/Molecules/MealPlan/MealAutocompleteCombobox.jsx';
import { MEAL_PLAN_TAG_OPTIONS } from '../../meal-library/mealTaxonomy.js';
import { SCHEDULER_SLOT_SECTIONS } from '../../meal-library/mealSearch.ts';

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

const PLANNING_DAY_COUNT = 7;

function slotKey(day, sectionKey, idx) {
    return `d${day}-${sectionKey}-${idx}`;
}

function createEmptySlotsById() {
    /** @type {Record<string, { mealQuery: string; mealId: number | null }>} */
    const out = {};
    for (let day = 1; day <= PLANNING_DAY_COUNT; day += 1) {
        for (const section of SCHEDULER_SLOT_SECTIONS) {
            for (let idx = 1; idx <= section.count; idx += 1) {
                out[slotKey(day, section.key, idx)] = { mealQuery: '', mealId: null };
            }
        }
    }
    return out;
}

/** @param {string[]} tags */
function resolvePlanCategory(tags) {
    const lowered = tags.map((tag) => String(tag).toLowerCase());
    if (lowered.some((tag) => tag.includes('sickle'))) {
        return 'sickle_cell_warrior';
    }
    if (lowered.some((tag) => tag.includes('hormone'))) {
        return 'cycle_sync';
    }
    return 'balanced';
}

/** @param {Record<string, { mealQuery: string; mealId: number | null }>} slotsById */
function buildSlotsPayload(slotsById) {
    /** @type {{ day_number: number; slot_type: string; slot_index: number; meal_id: number | null }[]} */
    const slots = [];
    for (let day = 1; day <= PLANNING_DAY_COUNT; day += 1) {
        for (const section of SCHEDULER_SLOT_SECTIONS) {
            for (let idx = 1; idx <= section.count; idx += 1) {
                const id = slotKey(day, section.key, idx);
                const state = slotsById[id] ?? { mealQuery: '', mealId: null };
                slots.push({
                    day_number: day,
                    slot_type: section.slotType,
                    slot_index: idx,
                    meal_id: state.mealId,
                });
            }
        }
    }
    return slots;
}

/**
 * @param {{
 *   formName: string;
 *   formDescription: string;
 *   targets: { calories: string; protein: string; carbs: string; fat: string };
 *   formTags: string[];
 *   formCyclePhase: string;
 *   slotsById: Record<string, { mealQuery: string; mealId: number | null }>;
 * }} input
 */
function validateSchedulerForm(input) {
    const errors = [];
    if (!input.formName.trim()) {
        errors.push('Enter a meal plan name.');
    }
    if (!input.formDescription.trim()) {
        errors.push('Enter a plan description and goal.');
    }
    const calories = Number.parseFloat(String(input.targets.calories).trim());
    if (!Number.isFinite(calories) || calories <= 0) {
        errors.push('Enter a daily calorie target greater than zero.');
    }
    if (resolvePlanCategory(input.formTags) === 'cycle_sync' && !input.formCyclePhase) {
        errors.push('Select a cycle phase for Hormone Feast plans.');
    }

    const missingSlots = [];
    for (let day = 1; day <= PLANNING_DAY_COUNT; day += 1) {
        for (const section of SCHEDULER_SLOT_SECTIONS) {
            for (let idx = 1; idx <= section.count; idx += 1) {
                const id = slotKey(day, section.key, idx);
                const state = input.slotsById[id];
                if (!state?.mealId) {
                    missingSlots.push(`${section.label} option ${idx} on ${WEEKDAY_LABELS[day - 1] ?? `day ${day}`}`);
                }
            }
        }
    }

    if (missingSlots.length > 0) {
        const preview = missingSlots.slice(0, 4).join(', ');
        const suffix = missingSlots.length > 4 ? ` (+${missingSlots.length - 4} more)` : '';
        errors.push(`Assign a meal to every slot. Still missing: ${preview}${suffix}.`);
    }

    return errors;
}

function resetCreateFormState() {
    return {
        formName: '',
        formTags: [],
        formDescription: '',
        formCyclePhase: '',
        targets: { calories: '', protein: '', carbs: '', fat: '' },
        activeDay: 1,
        slotsById: createEmptySlotsById(),
        saveErrors: [],
        serverError: null,
    };
}

/**
 * @param {{
 *   dietTypes?: { value: string; label: string }[];
 *   cyclePhases?: { value: string; label: string }[];
 *   mealSearchUrl?: string | null;
 *   mealPlanStoreUrl?: string | null;
 *   schedulerMeals?: import('../../meal-library/mealSearch.ts').MealPickerOption[];
 *   mealPlans?: { id: number; name: string; category: string; imageUrl?: string | null; tags: string[]; dailyMacros: { calories: number; protein: number; carbs: number; fat: number } }[];
 * }} props
 */
export function MealPlanLibraryPageContent({
    dietTypes = DEFAULT_DIET_TYPES,
    cyclePhases = DEFAULT_CYCLE_PHASES,
    mealSearchUrl: mealSearchUrlProp = null,
    mealPlanStoreUrl: mealPlanStoreUrlProp = null,
    schedulerMeals: schedulerMealsProp = [],
    mealPlans: mealPlansProp = [],
}) {
    const { props: pageProps } = usePage();
    const mealSearchUrl = resolveUrl(
        typeof mealSearchUrlProp === 'string' ? mealSearchUrlProp : null,
        mealPlanLibraryUrls(pageProps).mealSearch,
    );
    const mealPlanStoreUrl = resolveUrl(
        typeof mealPlanStoreUrlProp === 'string' ? mealPlanStoreUrlProp : null,
        mealPlanLibraryUrls(pageProps).store,
    );
    const schedulerMeals = Array.isArray(schedulerMealsProp) ? schedulerMealsProp : [];
    const mealPlans = Array.isArray(mealPlansProp) ? mealPlansProp : [];
    const flashSuccess = typeof pageProps.flash?.success === 'string' ? pageProps.flash.success : null;

    const [query, setQuery] = useState('');
    const [createOpen, setCreateOpen] = useState(false);
    const [saving, setSaving] = useState(false);

    // Search combobox (library header)
    const [searchOpen, setSearchOpen] = useState(false);
    const searchRootRef = useRef(null);
    const [searchMenuRect, setSearchMenuRect] = useState(null);

    // Create modal form state
    const [formName, setFormName] = useState('');
    const [formTags, setFormTags] = useState(/** @type {string[]} */ ([]));
    const [formDescription, setFormDescription] = useState('');
    const [formCyclePhase, setFormCyclePhase] = useState('');
    const [targets, setTargets] = useState({ calories: '', protein: '', carbs: '', fat: '' });
    const [saveErrors, setSaveErrors] = useState(/** @type {string[]} */ ([]));
    const [serverError, setServerError] = useState(/** @type {string | null} */ (null));

    const [activeDay, setActiveDay] = useState(1);

    const [slotsById, setSlotsById] = useState(createEmptySlotsById);

    const requiresCyclePhase = useMemo(() => resolvePlanCategory(formTags) === 'cycle_sync', [formTags]);

    const openCreateModal = useCallback(() => {
        const fresh = resetCreateFormState();
        setFormName(fresh.formName);
        setFormTags(fresh.formTags);
        setFormDescription(fresh.formDescription);
        setFormCyclePhase(fresh.formCyclePhase);
        setTargets(fresh.targets);
        setActiveDay(fresh.activeDay);
        setSlotsById(fresh.slotsById);
        setSaveErrors(fresh.saveErrors);
        setServerError(fresh.serverError);
        setCreateOpen(true);
    }, []);

    const closeCreateModal = useCallback(() => {
        setCreateOpen(false);
        setSaving(false);
        setSaveErrors([]);
        setServerError(null);
    }, []);

    const handleSaveMealPlan = useCallback(() => {
        const validationErrors = validateSchedulerForm({
            formName,
            formDescription,
            targets,
            formTags,
            formCyclePhase,
            slotsById,
        });

        if (validationErrors.length > 0) {
            setSaveErrors(validationErrors);
            setServerError(null);
            return;
        }

        if (!mealPlanStoreUrl) {
            setServerError('Save URL is not configured.');
            return;
        }

        const slots = buildSlotsPayload(slotsById).map((slot) => ({
            ...slot,
            meal_id: slot.meal_id,
        }));

        const payload = {
            name: formName.trim(),
            goal: formDescription.trim(),
            plan_category: resolvePlanCategory(formTags),
            cycle_phase: requiresCyclePhase ? formCyclePhase : null,
            target_daily_calories: Number.parseFloat(String(targets.calories).trim()),
            target_daily_protein_g: targets.protein.trim() === '' ? null : Number.parseFloat(targets.protein),
            target_daily_carbs_g: targets.carbs.trim() === '' ? null : Number.parseFloat(targets.carbs),
            target_daily_fat_g: targets.fat.trim() === '' ? null : Number.parseFloat(targets.fat),
            slots,
        };

        setSaving(true);
        setSaveErrors([]);
        setServerError(null);

        router.post(mealPlanStoreUrl, payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
            },
            onError: (errors) => {
                const messages = Object.values(errors).flatMap((value) => {
                    if (Array.isArray(value)) {
                        return value.map((item) => String(item));
                    }
                    return value ? [String(value)] : [];
                });
                setSaveErrors(messages.length > 0 ? messages : ['Could not save this meal plan. Check the form and try again.']);
            },
            onFinish: () => {
                setSaving(false);
            },
        });
    }, [
        closeCreateModal,
        formCyclePhase,
        formDescription,
        formName,
        formTags,
        mealPlanStoreUrl,
        requiresCyclePhase,
        slotsById,
        targets,
    ]);

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

    const filteredPlans = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return mealPlans;
        }
        return mealPlans.filter((p) => {
            const nameMatch = p.name.toLowerCase().includes(q);
            const categoryMatch = String(p.category ?? '')
                .toLowerCase()
                .includes(q);
            const tagMatch = Array.isArray(p.tags) && p.tags.some((t) => String(t).toLowerCase().includes(q));
            return nameMatch || categoryMatch || tagMatch;
        });
    }, [mealPlans, query]);

    const searchMatches = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q.length < 1) return [];
        return mealPlans
            .filter(
                (p) =>
                    p.name.toLowerCase().includes(q) ||
                    String(p.category ?? '')
                        .toLowerCase()
                        .includes(q) ||
                    p.tags.some((t) => String(t).toLowerCase().includes(q)),
            )
            .slice(0, 10);
    }, [mealPlans, query]);

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

                    {flashSuccess ? (
                        <p className="mx-5 mt-5 rounded-[12px] border border-[#5A6B44]/30 bg-[#F8F9F6] px-4 py-3 font-body text-sm text-[#262A22]">
                            {flashSuccess}
                        </p>
                    ) : null}

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
                                    onClick={openCreateModal}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <CSVUploader
                                    className="w-full pt-0"
                                    importCsvTemplateUrl="#"
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
                                            onPrimaryAction={() => {
                                                const url = p.showUrl ?? `/admin/meal-plan-library/${p.id}`;
                                                router.visit(url);
                                            }}
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
                        onClick={closeCreateModal}
                        aria-label="Close create meal plan modal"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Create new meal plan"
                        className="relative w-full max-w-[1200px] overflow-hidden rounded-[12px] bg-[#F8F9F6] shadow-2xl"
                    >
                        <div className="absolute right-3 top-3 z-20">
                            <Button label="Close" variant="ghost" onClick={closeCreateModal} />
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

                                        {requiresCyclePhase ? (
                                            <div className="block w-full text-left">
                                                <label
                                                    htmlFor="meal-plan-cycle-phase"
                                                    className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94"
                                                >
                                                    Cycle phase
                                                </label>
                                                <select
                                                    id="meal-plan-cycle-phase"
                                                    value={formCyclePhase}
                                                    onChange={(e) => setFormCyclePhase(e.target.value)}
                                                    className="block w-full rounded-[12px] border border-[#E5E7EB] bg-white px-4 py-3 font-body text-[15px] text-[#1F2937] shadow-sm outline-none focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                                >
                                                    <option value="">Select a phase…</option>
                                                    {cyclePhases.map((phase) => (
                                                        <option key={phase.value} value={phase.value}>
                                                            {phase.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        ) : null}

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

                                    <div className="mt-6 space-y-5">
                                        {SCHEDULER_SLOT_SECTIONS.map((section) => (
                                            <div key={section.key}>
                                                <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                                                    {section.label} ({section.count})
                                                </p>
                                                <div className="space-y-3">
                                                    {Array.from({ length: section.count }).map((_, idx0) => {
                                                        const idx = idx0 + 1;
                                                        const id = slotKey(activeDay, section.key, idx);
                                                        const state = slotsById[id] ?? { mealQuery: '', mealId: null };

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
                                                                <MealAutocompleteCombobox
                                                                    id={`meal-slot-combobox-${id}`}
                                                                    displayValue={state.mealQuery}
                                                                    mealId={state.mealId}
                                                                    categories={section.categories}
                                                                    searchUrl={mealSearchUrl}
                                                                    meals={schedulerMeals}
                                                                    onChange={({ displayValue, mealId: nextMealId }) => {
                                                                        setSlotsById((prev) => ({
                                                                            ...prev,
                                                                            [id]: { mealQuery: displayValue, mealId: nextMealId },
                                                                        }));
                                                                    }}
                                                                />
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
                                {saveErrors.length > 0 ? (
                                    <div
                                        className="mb-4 rounded-[12px] border border-red-200 bg-red-50 px-4 py-3"
                                        role="alert"
                                    >
                                        <p className="font-montserrat text-sm font-bold text-red-800">
                                            Complete the form before saving
                                        </p>
                                        <ul className="mt-2 list-disc space-y-1 pl-5 font-body text-sm text-red-700">
                                            {saveErrors.map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : null}
                                {serverError ? (
                                    <p className="mb-4 rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 font-body text-sm text-red-700" role="alert">
                                        {serverError}
                                    </p>
                                ) : null}
                                <div className="flex justify-end">
                                    <Button
                                        label={saving ? 'Saving…' : 'Save meal plan'}
                                        variant="primary"
                                        onClick={handleSaveMealPlan}
                                        disabled={saving}
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

MealPlanLibraryPage.layout = adminInertiaLayout;

export default MealPlanLibraryPage;
