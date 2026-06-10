import { resolveOnboardingTabStatus } from '../../../meal-craft/onboarding/onboardingTabFlow.js';

/**
 * @deprecated Replaced by {@link ./OnboardingNavHeader.jsx} — segmented progress + header lockup.
 *
 * Clickable onboarding step pills (tab controls).
 *
 * @param {{
 *   steps: Array<{ value: string; label: string }>;
 *   activeStep: string;
 *   onStepSelect: (step: string) => void;
 * }} props
 */
export default function OnboardingTabNav({ steps, activeStep, onStepSelect }) {
    return (
        <nav aria-label="Onboarding steps" className="w-full">
            <ol className="flex w-full flex-wrap items-center gap-2 md:gap-3">
                {steps.map((step) => {
                    const status = resolveOnboardingTabStatus(activeStep, step.value, steps);

                    const className =
                        status === 'active'
                            ? 'bg-[#556C37] text-white'
                            : status === 'complete'
                              ? 'bg-[#E8EFE0] text-[#556C37] hover:bg-[#d8e6c8]'
                              : 'border border-[#E5E7EB] bg-white text-[#6B7280] hover:border-[#556C37]/40 hover:bg-[#F8F9F6]';

                    return (
                        <li key={step.value} className="max-w-full">
                            <button
                                type="button"
                                onClick={() => onStepSelect(step.value)}
                                aria-current={status === 'active' ? 'step' : undefined}
                                className={`max-w-full truncate rounded-full px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide transition-colors sm:text-xs sm:tracking-wider ${className}`.trim()}
                            >
                                {step.label}
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
