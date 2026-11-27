import { useCallback, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import PeriodRangeCalendar from '../../Components/Molecules/Onboarding/PeriodRangeCalendar.jsx';
import ProtocolModeSelector from '../../Components/Molecules/Onboarding/ProtocolModeSelector.jsx';
import { toggleLoggedBleedDay } from '../../Components/Molecules/Onboarding/cycleSyncPhaseUtils.js';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/** @typedef {'analysis' | 'food' | 'exercise' | 'brain'} PeriodTrackerTabId */

const TABS = /** @type {const} */ ([
    { id: 'analysis', label: 'Analysis', icon: AnalysisIcon },
    { id: 'food', label: 'Food', icon: FoodIcon },
    { id: 'exercise', label: 'Exercise', icon: ExerciseIcon },
    { id: 'brain', label: 'Brain', icon: BrainIcon },
]);

/**
 * @param {{ className?: string }} props
 */
function AnalysisIcon({ className = '' }) {
    return (
        <svg className={className} width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-3"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function FoodIcon({ className = '' }) {
    return (
        <svg className={className} width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M12 3c-2.5 3.5-4 6.2-4 9a4 4 0 0 0 8 0c0-2.8-1.5-5.5-4-9Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
            />
            <path d="M12 16v5" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function ExerciseIcon({ className = '' }) {
    return (
        <svg className={className} width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M6.5 8.5 4 11l2.5 2.5M17.5 8.5 20 11l-2.5 2.5M8 11h8M9.5 6.5l1.2 1.2M14.5 6.5l-1.2 1.2M9.5 15.5l1.2-1.2M14.5 15.5l-1.2-1.2"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function BrainIcon({ className = '' }) {
    return (
        <svg className={className} width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M9 4a3 3 0 0 0-3 3v1a3 3 0 0 0-1 5.8V16a3 3 0 0 0 3 3h1M15 4a3 3 0 0 1 3 3v1a3 3 0 0 1 1 5.8V16a3 3 0 0 1-3 3h-1M12 4v16"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {object} props
 */
export function PeriodTrackerInner({
    customerName = '',
    tracker = null,
    updateCycleUrl = '/app/tools/period-tracker',
    craftedForYouUrl = '/consultation/crafted-for-you',
    toolsUrl = '/app/tools',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    cartUrl = '/app/cart',
}) {
    const page = usePage();
    const flashNotice = page.props?.flash?.mealPlanChangeNotice ?? tracker?.mealPlanChangeNotice ?? null;

    /** @type {[PeriodTrackerTabId, import('react').Dispatch<import('react').SetStateAction<PeriodTrackerTabId>>]} */
    const [activeTab, setActiveTab] = useState(/** @type {PeriodTrackerTabId} */ ('analysis'));
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [loggedPeriods, setLoggedPeriods] = useState(() => tracker?.loggedPeriods ?? []);
    const [cycleLength, setCycleLength] = useState(() => tracker?.cycleLength ?? 30);
    const [protocolMode, setProtocolMode] = useState(() => tracker?.protocolMode ?? 'non_fasting');

    const handleToggleBleedDay = useCallback((iso) => {
        setLoggedPeriods((current) => toggleLoggedBleedDay(current, iso));
    }, []);

    const current = tracker?.currentPhase ?? null;
    const phases = tracker?.phaseCatalog ?? [];
    const otherPhases = useMemo(() => phases.filter((phase) => !phase.isCurrent), [phases]);

    const handleSaveCycle = () => {
        setSaving(true);
        router.put(
            updateCycleUrl,
            {
                logged_periods: loggedPeriods,
                average_cycle_length: cycleLength,
                protocol_mode: protocolMode,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setEditing(false);
                },
            },
        );
    };

    const handleGenerateMealPlan = () => {
        const payload = tracker?.mealPlanGenerationPayload;
        if (!payload || !tracker?.isCycleSync) {
            return;
        }

        try {
            window.sessionStorage.setItem('mealCraft.cycleSyncMealPlanPayload', JSON.stringify(payload));
        } catch {
            // Ignore storage failures.
        }

        router.visit(craftedForYouUrl, {
            data: {
                craft_key: payload.craft_key,
                protocol_mode: payload.protocol_mode,
                phase_key: payload.phase_key,
            },
        });
    };

    const analysisPanel = (
        <div className="flex flex-col gap-4">
            {flashNotice ? (
                <div
                    className="rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 font-body text-sm text-amber-950"
                    role="status"
                >
                    {flashNotice}
                </div>
            ) : null}

            {current ? (
                <section
                    className="rounded-[16px] border border-border-light bg-white p-4 shadow-sm"
                    style={{ borderColor: `${current.color}55` }}
                >
                    <div className="flex items-start gap-3">
                        <span
                            className="mt-1 h-3 w-3 shrink-0 rounded-full"
                            style={{ backgroundColor: current.color }}
                            aria-hidden
                        />
                        <div className="min-w-0 flex-1">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-body">
                                {tracker?.cycleDay != null
                                    ? `Day ${tracker.cycleDay} of ${tracker.cycleLength}`
                                    : 'Log a bleed day to start tracking'}
                            </p>
                            <h2 className="mt-1 font-montserrat text-xl font-semibold text-heading">
                                {current.phaseName}
                            </h2>
                            <p className="mt-0.5 font-body text-sm text-body">{current.biologicalPhase}</p>
                            {current.startDay != null && current.endDay != null ? (
                                <p className="mt-1 font-body text-xs text-body/80">
                                    Days {current.startDay}–{current.endDay}
                                </p>
                            ) : null}
                            <p className="mt-3 font-body text-sm leading-relaxed text-body">
                                {current.analysisSummary}
                            </p>
                        </div>
                    </div>
                </section>
            ) : (
                <p className="rounded-[12px] border border-dashed border-border-light bg-white px-4 py-6 text-center font-body text-sm text-body">
                    Log your period days to see your current phase.
                </p>
            )}

            <Button
                type="button"
                label="Edit Cycle"
                variant="secondary"
                className="w-full uppercase tracking-[0.08em]"
                onClick={() => setEditing(true)}
            />

            <section className="flex flex-col gap-2.5">
                <h3 className="font-montserrat text-sm font-bold uppercase tracking-[0.1em] text-heading">
                    All phases
                </h3>
                {phases.map((phase) => (
                    <div
                        key={phase.id}
                        className="rounded-[12px] border border-border-light bg-white px-3 py-3"
                    >
                        <div className="flex items-center gap-2">
                            <span
                                className="h-2.5 w-2.5 rounded-full"
                                style={{ backgroundColor: phase.color }}
                                aria-hidden
                            />
                            <p className="font-montserrat text-sm font-semibold text-heading">
                                {phase.phaseName}
                                <span className="font-body font-normal text-body">
                                    {' '}
                                    · {phase.subLabel}
                                </span>
                            </p>
                            {phase.isCurrent ? (
                                <span className="ms-auto rounded-full bg-brand-primary/15 px-2 py-0.5 font-montserrat text-[10px] font-bold uppercase tracking-wide text-brand-primary">
                                    Now
                                </span>
                            ) : null}
                        </div>
                        {phase.startDay != null && phase.endDay != null ? (
                            <p className="mt-1 font-body text-xs text-body/80">
                                Days {phase.startDay}–{phase.endDay}
                            </p>
                        ) : null}
                        <p className="mt-1.5 font-body text-sm leading-relaxed text-body">
                            {phase.analysisSummary}
                        </p>
                    </div>
                ))}
            </section>
        </div>
    );

    const foodPanel = current ? (
        <div className="flex flex-col gap-4">
            <section className="rounded-[16px] border border-border-light bg-white p-4">
                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-body">
                    Food approach
                </p>
                <h2 className="mt-1 font-montserrat text-xl font-semibold text-heading">
                    {current.foodApproach}
                </h2>
                <p className="mt-2 font-body text-sm text-body">
                    Fasting: <span className="font-semibold">{current.fastingWindow}</span>
                </p>
                <p className="mt-3 font-body text-sm leading-relaxed text-body">{current.whyDescription}</p>
            </section>
            {tracker?.isCycleSync ? (
                <Button
                    type="button"
                    label={current.mealPlanCtaLabel}
                    className="w-full uppercase tracking-[0.08em]"
                    onClick={handleGenerateMealPlan}
                />
            ) : (
                <p className="font-body text-sm text-body">
                    Meal-plan regeneration is available when your diet protocol is Cycle Sync.
                </p>
            )}
        </div>
    ) : (
        <p className="font-body text-sm text-body">Log a cycle to see food guidance.</p>
    );

    const exercisePanel = (
        <div className="flex flex-col gap-3">
            {(current ? [current, ...otherPhases] : phases).map((phase) => (
                <section key={phase.id} className="rounded-[12px] border border-border-light bg-white p-4">
                    <div className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: phase.color }} />
                        <h3 className="font-montserrat text-sm font-semibold text-heading">
                            {phase.phaseName} · {phase.subLabel}
                        </h3>
                    </div>
                    <p className="mt-2 font-body text-sm leading-relaxed text-body">
                        <span className="font-semibold">Focus: </span>
                        {phase.exerciseFocus}
                    </p>
                    <p className="mt-1.5 font-body text-sm leading-relaxed text-body">
                        <span className="font-semibold">Why: </span>
                        {phase.exerciseWhy}
                    </p>
                </section>
            ))}
        </div>
    );

    const brainPanel = (
        <div className="flex flex-col gap-3">
            {(current ? [current, ...otherPhases] : phases).map((phase) => (
                <section key={phase.id} className="rounded-[12px] border border-border-light bg-white p-4">
                    <div className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: phase.color }} />
                        <h3 className="font-montserrat text-sm font-semibold text-heading">
                            {phase.phaseName} · {phase.subLabel}
                        </h3>
                    </div>
                    <p className="mt-2 font-body text-sm leading-relaxed text-body">
                        <span className="font-semibold">Brain & focus: </span>
                        {phase.brainFocus}
                    </p>
                    <p className="mt-1.5 font-body text-sm leading-relaxed text-body">
                        <span className="font-semibold">What to do: </span>
                        {phase.brainWhatToDo}
                    </p>
                </section>
            ))}
        </div>
    );

    const tabBody =
        activeTab === 'food'
            ? foodPanel
            : activeTab === 'exercise'
              ? exercisePanel
              : activeTab === 'brain'
                ? brainPanel
                : analysisPanel;

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            onBack={() => router.visit(toolsUrl)}
            backAriaLabel="Back to My Tools"
            footerNav={
                <CustomerAppBottomNav
                    activeTab="tools"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    toolsUrl={toolsUrl}
                    favouritesUrl={page.props?.favouritesUrl ?? '/app/favourites'}
                />
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <div
                    className="flex items-center justify-between gap-2 px-1"
                    role="tablist"
                    aria-label="Period Tracker"
                >
                    {TABS.map((tab) => {
                        const selected = activeTab === tab.id;
                        const Icon = tab.icon;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={selected}
                                aria-label={tab.label}
                                onClick={() => setActiveTab(/** @type {PeriodTrackerTabId} */ (tab.id))}
                                className={[
                                    'flex flex-1 flex-col items-center gap-1.5 rounded-[12px] py-2 outline-none transition',
                                    'focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2',
                                ].join(' ')}
                            >
                                <span
                                    className={[
                                        'flex h-12 w-12 items-center justify-center rounded-full border-2 transition',
                                        selected
                                            ? 'border-[#6E8C47] bg-brand-primary/15 text-[#6E8C47]'
                                            : 'border-transparent bg-brand-primary/10 text-body',
                                    ].join(' ')}
                                >
                                    <Icon />
                                </span>
                                <span
                                    className={[
                                        'font-montserrat text-[10px] font-bold uppercase tracking-[0.08em]',
                                        selected ? 'text-[#6E8C47]' : 'text-body',
                                    ].join(' ')}
                                >
                                    {tab.label}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="pb-4">{tabBody}</div>
            </div>

            {editing ? (
                <div className="fixed inset-0 z-[120] flex items-end justify-center bg-black/40 p-3 sm:items-center">
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="edit-cycle-title"
                        className="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-[16px] border border-border-light bg-white p-4 shadow-xl"
                    >
                        <h2
                            id="edit-cycle-title"
                            className="font-montserrat text-lg font-semibold text-heading"
                        >
                            Edit Cycle
                        </h2>
                        <p className="mt-1 font-body text-sm text-body">
                            Update bleed days, cycle length, and fasting preference.
                        </p>

                        <div className="mt-4">
                            <PeriodRangeCalendar
                                loggedPeriods={loggedPeriods}
                                onToggleBleedDay={handleToggleBleedDay}
                                cycleLength={cycleLength}
                            />
                        </div>

                        <label className="mt-4 block font-montserrat text-xs font-bold uppercase tracking-[0.08em] text-body">
                            Cycle length (days)
                            <input
                                type="number"
                                min={21}
                                max={45}
                                value={cycleLength}
                                onChange={(event) => setCycleLength(Number(event.target.value) || 30)}
                                className="mt-1.5 w-full rounded-[12px] border border-border-light px-3 py-2.5 font-body text-sm text-heading"
                            />
                        </label>

                        <div className="mt-4">
                            <ProtocolModeSelector value={protocolMode} onChange={setProtocolMode} />
                        </div>

                        <div className="mt-5 flex flex-col gap-2">
                            <Button
                                type="button"
                                label={saving ? 'Saving…' : 'Save changes'}
                                disabled={saving || loggedPeriods.length === 0}
                                className="w-full uppercase tracking-[0.08em]"
                                onClick={handleSaveCycle}
                            />
                            <Button
                                type="button"
                                label="Cancel"
                                variant="ghost"
                                className="w-full uppercase tracking-[0.08em]"
                                onClick={() => setEditing(false)}
                            />
                        </div>
                    </div>
                </div>
            ) : null}
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function PeriodTracker(props) {
    return <PeriodTrackerInner {...props} />;
}

PeriodTracker.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
