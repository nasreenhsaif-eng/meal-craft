import {
    CYCLE_SYNC_PHASE_COLORS,
    CYCLE_SYNC_PHASE_GUIDANCE,
    LOGGED_BLEED_COLOR,
} from './cycleSyncPhaseUtils.js';

/**
 * @param {{
 *   swatchClassName?: string;
 *   label: string;
 *   subtitle?: string;
 *   detail: string;
 *   style?: Record<string, string>;
 * }} props
 */
function LegendItem({ swatchClassName = '', label, subtitle, detail, style }) {
    return (
        <div className="inline-flex min-w-0 items-start gap-2">
            <span
                className={`mt-0.5 inline-block h-3 w-8 shrink-0 rounded-full ${swatchClassName}`.trim()}
                style={style}
                aria-hidden="true"
            />
            <span className="min-w-0">
                <span className="block font-montserrat text-xs font-semibold text-body">{label}</span>
                {subtitle ? (
                    <span className="block font-montserrat text-[11px] font-medium text-body/80">{subtitle}</span>
                ) : null}
                <span className="block font-montserrat text-[11px] font-medium text-form-label">{detail}</span>
            </span>
        </div>
    );
}

/**
 * @param {import('./cycleSyncPhaseUtils.js').CycleSyncPhaseId} phaseId
 */
function phaseLegendItem(phaseId) {
    const guidance = CYCLE_SYNC_PHASE_GUIDANCE[phaseId];

    return (
        <LegendItem
            key={phaseId}
            label={guidance.phaseName}
            subtitle={guidance.subLabel}
            detail={`${guidance.fastingWindow} / ${guidance.foodApproach}`}
            style={{ backgroundColor: CYCLE_SYNC_PHASE_COLORS[phaseId] }}
        />
    );
}

/**
 * Legend for Cycle Sync period-tracking calendar colors and fasting guidance.
 */
export default function CycleCalendarLegend({ className = '' }) {
    return (
        <div
            className={`flex flex-wrap items-start justify-center gap-x-4 gap-y-3 rounded-[12px] border border-border-light bg-surface-soft px-4 py-3 ${className}`.trim()}
            aria-label="Calendar legend"
        >
            <LegendItem
                label="Logged Period"
                subtitle="Bleed day override"
                detail="Crimson overrides phase color"
                style={{ backgroundColor: LOGGED_BLEED_COLOR }}
            />
            {phaseLegendItem('power_phase_1')}
            {phaseLegendItem('manifestation')}
            {phaseLegendItem('power_phase_2')}
            {phaseLegendItem('nurture_phase')}
        </div>
    );
}
