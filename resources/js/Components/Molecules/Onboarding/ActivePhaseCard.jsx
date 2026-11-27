import { router } from '@inertiajs/react';
import Button from '../../Atoms/Button/Button.jsx';
import ProtocolModeSelector from './ProtocolModeSelector.jsx';
import {
    DEFAULT_PROTOCOL_MODE,
    mealPlanGenerationPayloadFor,
    normalizeProtocolMode,
    phaseGuidanceFor,
} from './cycleSyncPhaseUtils.js';

/**
 * @param {{
 *   phaseId?: string | null;
 *   protocolMode?: string | null;
 *   cycleDay?: number | null;
 *   cycleLength?: number | null;
 *   metadata?: Record<string, unknown> | null;
 *   mealPlanPayload?: Record<string, unknown> | null;
 *   onProtocolModeChange?: (value: 'non_fasting' | 'fasting') => void;
 *   updateProtocolModeUrl?: string | null;
 *   craftedForYouUrl?: string;
 *   processing?: boolean;
 *   showProtocolSelector?: boolean;
 *   changeProtocolUrl?: string | null;
 *   className?: string;
 * }} props
 */
export default function ActivePhaseCard({
    phaseId = null,
    protocolMode = DEFAULT_PROTOCOL_MODE,
    cycleDay = null,
    cycleLength = null,
    metadata = null,
    mealPlanPayload = null,
    onProtocolModeChange,
    updateProtocolModeUrl = null,
    craftedForYouUrl = '/consultation/crafted-for-you',
    processing = false,
    showProtocolSelector = true,
    changeProtocolUrl = null,
    className = '',
}) {
    const mode = normalizeProtocolMode(protocolMode);
    const resolvedPhaseId =
        phaseId === 'power_phase_1' ||
        phaseId === 'manifestation' ||
        phaseId === 'power_phase_2' ||
        phaseId === 'nurture_phase'
            ? phaseId
            : null;

    const guidance = resolvedPhaseId
        ? phaseGuidanceFor(resolvedPhaseId, mode)
        : null;

    const phaseName = guidance?.phaseName ?? metadata?.phase_name ?? metadata?.phaseName ?? 'Active phase';
    const biologicalPhase =
        guidance?.biologicalPhase ?? metadata?.biological_phase ?? metadata?.biologicalPhase ?? null;
    const subLabel = guidance?.subLabel ?? metadata?.sub_label ?? metadata?.subLabel ?? null;
    const foodApproach =
        guidance?.foodApproach ?? metadata?.food_approach ?? metadata?.foodApproach ?? null;
    const fastingWindow =
        guidance?.fastingWindow ?? metadata?.fasting_window ?? metadata?.fastingWindow ?? null;
    const whyDescription =
        guidance?.whyDescription ?? metadata?.why_description ?? metadata?.whyDescription ?? null;
    const color = guidance?.color ?? metadata?.color ?? '#6E8C47';
    const ctaLabel =
        guidance?.mealPlanCtaLabel ?? metadata?.meal_plan_cta_label ?? 'Generate my meal plan';
    const payload =
        mealPlanPayload ??
        (resolvedPhaseId ? mealPlanGenerationPayloadFor(resolvedPhaseId, mode) : null);

    const handleModeChange = (nextMode) => {
        onProtocolModeChange?.(nextMode);

        if (updateProtocolModeUrl) {
            router.put(
                updateProtocolModeUrl,
                { protocol_mode: nextMode },
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    const handleGenerate = () => {
        if (!payload) {
            return;
        }

        try {
            window.sessionStorage.setItem('mealCraft.cycleSyncMealPlanPayload', JSON.stringify(payload));
        } catch {
            // Ignore quota / private-mode failures; CTA still navigates.
        }

        router.visit(craftedForYouUrl, {
            data: {
                craft_key: payload.craft_key,
                protocol_mode: payload.protocol_mode,
                phase_key: payload.phase_key,
                biological_phase: payload.biological_phase,
                food_approach: payload.food_approach,
                fasting_window: payload.fasting_window,
                fasting_type: payload.fasting_type,
                schedules_full_daily_meals: payload.schedules_full_daily_meals,
            },
        });
    };

    return (
        <section
            className={`w-full rounded-[16px] border border-border-light bg-white px-4 py-5 shadow-sm sm:px-5 ${className}`.trim()}
            aria-label="Active cycle phase"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-montserrat text-[10px] font-bold uppercase tracking-[0.14em] text-form-label">
                        Active phase
                    </p>
                    <h2 className="mt-1 font-montserrat text-xl font-bold tracking-tight text-heading">
                        {phaseName}
                    </h2>
                    {biologicalPhase ? (
                        <p className="mt-1 font-montserrat text-sm font-medium text-body">{biologicalPhase}</p>
                    ) : null}
                    {subLabel ? (
                        <p className="mt-0.5 font-montserrat text-xs font-medium text-form-label">{subLabel}</p>
                    ) : null}
                </div>
                <span
                    className="mt-1 inline-block h-3 w-3 shrink-0 rounded-full"
                    style={{ backgroundColor: String(color) }}
                    aria-hidden
                />
            </div>

            {cycleDay != null ? (
                <p className="mt-3 font-montserrat text-xs font-semibold uppercase tracking-[0.08em] text-form-label">
                    Cycle day {cycleDay}
                    {cycleLength != null ? ` · ${cycleLength}-day cycle` : ''}
                </p>
            ) : (
                <p className="mt-3 font-montserrat text-sm text-body">
                    Log your period to unlock the live active phase card.
                </p>
            )}

            {showProtocolSelector ? (
                <div className="mt-4">
                    <ProtocolModeSelector
                        value={mode}
                        onChange={handleModeChange}
                        disabled={processing}
                    />
                </div>
            ) : null}

            {guidance || metadata ? (
                <div className="mt-4 flex flex-wrap gap-2">
                    {foodApproach ? (
                        <span className="inline-flex rounded-full bg-brand-primary/10 px-3 py-1 font-montserrat text-xs font-semibold text-brand-primary-pressed">
                            {String(foodApproach)}
                        </span>
                    ) : null}
                    {fastingWindow ? (
                        <span
                            className="inline-flex rounded-full px-3 py-1 font-montserrat text-xs font-semibold"
                            style={{
                                backgroundColor: `${color}1A`,
                                color: String(color),
                            }}
                        >
                            {String(fastingWindow)}
                        </span>
                    ) : null}
                </div>
            ) : null}

            {whyDescription ? (
                <p className="mt-4 font-montserrat text-sm font-medium leading-relaxed text-body">
                    {String(whyDescription)}
                </p>
            ) : null}

            {changeProtocolUrl ? (
                <div className="mt-4 flex justify-start">
                    <a
                        href={changeProtocolUrl}
                        className="inline-flex items-center rounded-full border border-brand-primary/30 bg-brand-primary/10 px-3 py-1.5 font-montserrat text-xs font-semibold text-brand-primary-pressed hover:bg-brand-primary/15"
                    >
                        Change protocol preference
                    </a>
                </div>
            ) : null}

            {payload ? (
                <div className="mt-5 flex w-full justify-center">
                    <Button
                        type="button"
                        label={ctaLabel}
                        onClick={handleGenerate}
                        disabled={processing || !resolvedPhaseId}
                        className="min-w-[220px] uppercase tracking-[0.06em]"
                    />
                </div>
            ) : null}
        </section>
    );
}
