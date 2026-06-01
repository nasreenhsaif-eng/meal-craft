import { useMemo } from 'react';
import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import {
    calculateDailyTargets,
    formatCalorieTarget,
    formatMacroGrams,
    formatMacroPercentage,
} from '../../meal-craft/dailyTargetsCalculator.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.js';
import { OnboardingShell } from './Welcome.jsx';

const MACRO_THEMES = {
    protein: {
        barClassName: 'bg-status-error',
        cardClassName: 'border-status-error/20 bg-status-error/10',
        valueClassName: 'text-status-error',
        labelClassName: 'text-status-error',
    },
    carbs: {
        barClassName: 'bg-[#606c4e]',
        cardClassName: 'border-[#606c4e]/20 bg-[#606c4e]/10',
        valueClassName: 'text-[#606c4e]',
        labelClassName: 'text-[#606c4e]',
    },
    fat: {
        barClassName: 'bg-brand-secondary',
        cardClassName: 'border-brand-secondary/20 bg-brand-secondary/10',
        valueClassName: 'text-brand-secondary',
        labelClassName: 'text-brand-secondary',
    },
};

function CheckmarkBadge() {
    return (
        <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-primary/15 text-brand-primary-pressed ring-1 ring-brand-primary/30">
            <svg className="block h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                    d="M6 12.5 10 16.5 18 8.5"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </span>
    );
}

/**
 * @param {{
 *   label: string;
 *   value: string;
 *   subLabel: string;
 *   className?: string;
 * }} props
 */
function TargetSummaryCard({ label, value, subLabel, className = '' }) {
    return (
        <article
            className={`rounded-[12px] border border-border-light bg-grey-96 px-5 py-4 text-center ${className}`.trim()}
        >
            <p className="font-montserrat text-[11px] font-bold uppercase tracking-[0.14em] text-grey-94">
                {label}
            </p>
            <p className="mt-2 font-montserrat text-3xl font-bold leading-none text-brand-primary-pressed">
                {value}
            </p>
            <p className="mt-1 font-montserrat text-sm font-medium text-grey-33">{subLabel}</p>
        </article>
    );
}

/**
 * @param {{
 *   name: string;
 *   grams: number;
 *   percentage: number;
 *   theme: keyof typeof MACRO_THEMES;
 * }} props
 */
function MacroDetailCard({ name, grams, percentage, theme }) {
    const styles = MACRO_THEMES[theme];

    return (
        <div
            className={`flex min-w-0 flex-1 flex-col items-center rounded-[12px] border px-2 py-4 text-center sm:px-3 ${styles.cardClassName}`.trim()}
        >
            <p className={`font-montserrat text-lg font-bold leading-none sm:text-xl ${styles.valueClassName}`}>
                {formatMacroGrams(grams)}
            </p>
            <p
                className={`mt-2 font-montserrat text-xs font-semibold leading-none whitespace-nowrap sm:text-sm ${styles.labelClassName}`}
            >
                {name} • {formatMacroPercentage(percentage)}
            </p>
        </div>
    );
}

/**
 * @param {import('../../meal-craft/dailyTargetsCalculator.js').DailyTargetsResult} targets
 * @param {{
 *   onStartPlan?: () => void;
 *   processing?: boolean;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 * }} props
 */
export function DailyTargetsSummaryInner({
    targets,
    onStartPlan,
    processing = false,
    steps = [],
    currentStep = 'daily_targets',
    customerName = '',
}) {
    if (!targets) {
        return null;
    }

    return (
        <OnboardingShell
            title="Your Daily Targets"
            description=""
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            hideDefaultHeader
        >
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <header className="flex flex-col items-center gap-2 text-center">
                    <CheckmarkBadge />
                    <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-brand-primary-pressed sm:text-3xl">
                        Your Daily Targets
                    </h1>
                </header>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TargetSummaryCard
                        label="Calorie Target"
                        value={formatCalorieTarget(targets.dailyCalories)}
                        subLabel="kcal / day"
                    />
                    <TargetSummaryCard
                        label="Protein Target"
                        value={formatMacroGrams(targets.proteinGrams)}
                        subLabel="per day"
                    />
                </div>

                <section
                    className="rounded-[12px] border border-border-light bg-grey-96 px-4 py-5 sm:px-5"
                    aria-labelledby="macro-breakdown-heading"
                >
                    <h2
                        id="macro-breakdown-heading"
                        className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-grey-94"
                    >
                        Macro Breakdown
                    </h2>

                    <div
                        className="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-white ring-1 ring-border-light"
                        role="img"
                        aria-label={`Protein ${targets.proteinPercentage} percent, carbs ${targets.carbPercentage} percent, fat ${targets.fatPercentage} percent`}
                    >
                        <div
                            className={`h-full ${MACRO_THEMES.protein.barClassName}`}
                            style={{ width: `${targets.proteinPercentage}%` }}
                        />
                        <div
                            className={`h-full ${MACRO_THEMES.carbs.barClassName}`}
                            style={{ width: `${targets.carbPercentage}%` }}
                        />
                        <div
                            className={`h-full ${MACRO_THEMES.fat.barClassName}`}
                            style={{ width: `${targets.fatPercentage}%` }}
                        />
                    </div>

                    <div className="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                        <MacroDetailCard
                            name="Protein"
                            grams={targets.proteinGrams}
                            percentage={targets.proteinPercentage}
                            theme="protein"
                        />
                        <MacroDetailCard
                            name="Carbs"
                            grams={targets.carbGrams}
                            percentage={targets.carbPercentage}
                            theme="carbs"
                        />
                        <MacroDetailCard
                            name="Fat"
                            grams={targets.fatGrams}
                            percentage={targets.fatPercentage}
                            theme="fat"
                        />
                    </div>
                </section>

                <div className="flex justify-center pt-2">
                    <Button
                        type="button"
                        label="Craft my plan"
                        disabled={processing}
                        onClick={onStartPlan}
                        className="!inline-flex !h-[50px] !min-h-[50px] w-auto min-w-[240px] max-w-xs shrink-0 whitespace-nowrap px-12 normal-case tracking-normal bg-[#606c4e] hover:bg-brand-primary-pressed disabled:opacity-60"
                    />
                </div>
            </div>
        </OnboardingShell>
    );
}

export default function DailyTargetsSummary() {
    const onboarding = onboardingFromPage(usePage().props);
    const { computedTargets, profileInput } = useOnboardingStore();

    const targets = useMemo(
        () => computedTargets ?? calculateDailyTargets(profileInput),
        [computedTargets, profileInput],
    );

    return (
        <DailyTargetsSummaryInner
            targets={targets}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'daily_targets'}
            customerName={onboarding.customerName ?? ''}
            onStartPlan={() => router.post(onboarding.urls?.dailyTargets ?? '/onboarding/daily-targets')}
        />
    );
}

DailyTargetsSummary.layout = customerOnboardingLayout;
