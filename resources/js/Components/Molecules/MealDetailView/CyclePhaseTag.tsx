import type { ReactElement } from 'react';

export type CyclePhase = 'Menstrual' | 'Follicular' | 'Ovulatory' | 'Luteal';

const PHASE_STYLES: Record<CyclePhase, string> = {
    Menstrual:
        'border-rose-200/90 bg-rose-50/90 text-rose-950 ring-1 ring-inset ring-rose-100/80 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-100 dark:ring-rose-900/30',
    Follicular:
        'border-emerald-200/90 bg-emerald-50/90 text-emerald-950 ring-1 ring-inset ring-emerald-100/80 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-900/30',
    Ovulatory:
        'border-amber-200/90 bg-amber-50/90 text-amber-950 ring-1 ring-inset ring-amber-100/80 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-900/30',
    Luteal:
        'border-violet-200/90 bg-violet-50/90 text-violet-950 ring-1 ring-inset ring-violet-100/80 dark:border-violet-900/40 dark:bg-violet-950/40 dark:text-violet-100 dark:ring-violet-900/30',
};

export type CyclePhaseTagProps = {
    phase: CyclePhase;
    className?: string;
};

export function CyclePhaseTag({ phase, className = '' }: CyclePhaseTagProps): ReactElement {
    return (
        <span
            role="status"
            aria-label={`Cycle phase: ${phase}`}
            className={[
                'box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center rounded-full border px-3 py-1 font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide whitespace-nowrap',
                PHASE_STYLES[phase],
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {phase}
        </span>
    );
}
