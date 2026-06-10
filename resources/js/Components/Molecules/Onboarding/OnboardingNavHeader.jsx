import MealCraftLogo from '../../Atoms/Logo/MealCraftLogo.jsx';
import {
    getOnboardingStepIndex,
    resolveOnboardingTabStatus,
} from '../../../meal-craft/onboarding/onboardingTabFlow.js';

/**
 * Onboarding top chrome — back control, centered lockup, step counter, segmented progress.
 *
 * @param {{
 *   steps: Array<{ value: string; label: string }>;
 *   activeStep: string;
 *   onBack?: () => void;
 *   canGoBack?: boolean;
 * }} props
 */
export default function OnboardingNavHeader({ steps, activeStep, onBack, canGoBack = false }) {
    const totalSteps = steps.length;
    const activeIndex = getOnboardingStepIndex(activeStep, steps);
    const displayIndex = activeIndex >= 0 ? activeIndex + 1 : 1;

    return (
        <header className="w-full" aria-label="Onboarding navigation">
            <div className="grid w-full grid-cols-[2.5rem_1fr_auto] items-center gap-x-2">
                <button
                    type="button"
                    onClick={onBack}
                    disabled={!canGoBack}
                    aria-label="Go back to previous step"
                    className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-base leading-none text-[#262A22] outline-none transition [-webkit-tap-highlight-color:transparent] hover:bg-gray-100 focus:outline-none focus:ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    ←
                </button>

                <div className="flex min-w-0 justify-center">
                    <MealCraftLogo variant="minimal" width={108} alt="Meal Craft" className="h-auto max-w-full" />
                </div>

                <p className="shrink-0 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">
                    Step {displayIndex} of {totalSteps}
                </p>
            </div>

            <div
                className="mt-3 grid w-full gap-1.5"
                style={{ gridTemplateColumns: `repeat(${Math.max(totalSteps, 1)}, minmax(0, 1fr))` }}
                role="progressbar"
                aria-valuemin={1}
                aria-valuemax={totalSteps}
                aria-valuenow={displayIndex}
                aria-label={`Onboarding step ${displayIndex} of ${totalSteps}`}
            >
                {steps.map((step) => {
                    const status = resolveOnboardingTabStatus(activeStep, step.value, steps);
                    const filled = status === 'complete' || status === 'active';

                    return (
                        <div
                            key={step.value}
                            className={`h-1 rounded-full transition-all duration-300 ${filled ? 'bg-[#6E8C47]' : 'bg-gray-100'}`}
                            aria-hidden="true"
                        />
                    );
                })}
            </div>
        </header>
    );
}
