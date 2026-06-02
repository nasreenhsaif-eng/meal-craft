import { useCallback, useEffect, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import AverageCycleLengthMetric from '../../Components/Molecules/Onboarding/AverageCycleLengthMetric.jsx';
import LoggedPeriodsList from '../../Components/Molecules/Onboarding/LoggedPeriodsList.jsx';
import PeriodRangeCalendar from '../../Components/Molecules/Onboarding/PeriodRangeCalendar.jsx';
import {
    DEFAULT_CYCLE_LENGTH_DAYS,
    resolveAverageCycleLengthMetric,
} from '../../Components/Molecules/Onboarding/cyclePredictionUtils.js';
import {
    appendPeriodIfMissing,
    removePeriodByKey,
} from '../../Components/Molecules/Onboarding/periodTrackingUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import { OnboardingShell } from './Welcome.jsx';

/**
 * @typedef {{ start: string; end: string }} LoggedPeriod
 */

/**
 * @param {{ start: string | null; end: string | null }} range
 * @returns {range is { start: string; end: string }}
 */
function isCompletedRange(range) {
    return Boolean(range.start && range.end);
}

/**
 * Period tracking onboarding step (female customers only).
 *
 * @param {{
 *   loggedPeriods?: LoggedPeriod[];
 *   averageCycleLength?: number;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onLoggedPeriodsChange?: (value: LoggedPeriod[]) => void;
 *   onAverageCycleLengthChange?: (value: number) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 * }} props
 */
export function OnboardingPeriodTrackingInner({
    loggedPeriods: loggedPeriodsProp,
    averageCycleLength: averageCycleLengthProp,
    errors = {},
    processing = false,
    onLoggedPeriodsChange,
    onAverageCycleLengthChange,
    onSubmit,
    steps = [],
    currentStep = 'period_tracking',
    customerName = '',
}) {
    const [demoPeriods, setDemoPeriods] = useState(/** @type {LoggedPeriod[]} */ ([]));
    const [draftRange, setDraftRange] = useState({ start: null, end: null });
    const [avgCycleLength, setAvgCycleLength] = useState(
        averageCycleLengthProp ?? DEFAULT_CYCLE_LENGTH_DAYS,
    );
    const [usesStandardCycleLength, setUsesStandardCycleLength] = useState(true);
    const loggedPeriods = loggedPeriodsProp ?? demoPeriods;
    const setLoggedPeriods = onLoggedPeriodsChange ?? setDemoPeriods;

    useEffect(() => {
        if (!isCompletedRange(draftRange)) {
            return;
        }

        setLoggedPeriods((current) =>
            appendPeriodIfMissing(current, { start: draftRange.start, end: draftRange.end }),
        );
        setDraftRange({ start: null, end: null });
    }, [draftRange, setLoggedPeriods]);

    useEffect(() => {
        const metric = resolveAverageCycleLengthMetric(loggedPeriods);

        setAvgCycleLength(metric.days);
        setUsesStandardCycleLength(metric.isStandard);
        onAverageCycleLengthChange?.(metric.days);
    }, [loggedPeriods, onAverageCycleLengthChange]);

    return (
        <OnboardingShell
            title="Track your period"
            description="Log recent cycles so we can personalize nutrition recommendations across your menstrual phases."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
            titleClassName="text-brand-primary-pressed"
        >
            <form
                className="mx-auto flex w-full max-w-xl flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();
                }}
            >
                <div className="flex w-full justify-center">
                    <PeriodRangeCalendar
                        rangeValue={draftRange}
                        onRangeChange={setDraftRange}
                        loggedPeriods={loggedPeriods}
                        onLoggedPeriodRemove={(key) =>
                            setLoggedPeriods((current) => removePeriodByKey(current, key))
                        }
                    />
                </div>

                <AverageCycleLengthMetric
                    days={avgCycleLength}
                    isStandard={usesStandardCycleLength}
                />

                <LoggedPeriodsList
                    periods={loggedPeriods}
                    onRemove={(key) => setLoggedPeriods((current) => removePeriodByKey(current, key))}
                />

                {errors.logged_periods ? (
                    <p className="text-center text-sm text-status-error" role="alert">
                        {errors.logged_periods}
                    </p>
                ) : null}

                {errors.average_cycle_length ? (
                    <p className="text-center text-sm text-status-error" role="alert">
                        {errors.average_cycle_length}
                    </p>
                ) : null}

                <div className="flex w-full justify-center">
                    <Button
                        type="submit"
                        label={processing ? 'Saving…' : 'Next'}
                        disabled={processing}
                        className="min-w-[200px] uppercase tracking-[0.08em]"
                    />
                </div>
            </form>
        </OnboardingShell>
    );
}

export default function PeriodTracking() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        logged_periods: state.periodTracking.loggedPeriods.length
            ? state.periodTracking.loggedPeriods
            : (profile.logged_periods ?? profile.loggedPeriods ?? []),
        average_cycle_length:
            state.periodTracking.averageCycleLength ??
            profile.average_cycle_length ??
            profile.averageCycleLength ??
            DEFAULT_CYCLE_LENGTH_DAYS,
    });

    const handleAverageCycleLengthChange = useCallback(
        (next) => {
            setData('average_cycle_length', next);
        },
        [setData],
    );

    return (
        <OnboardingPeriodTrackingInner
            loggedPeriods={data.logged_periods}
            averageCycleLength={data.average_cycle_length}
            errors={errors}
            processing={processing}
            onLoggedPeriodsChange={(next) => {
                const resolved =
                    typeof next === 'function' ? next(data.logged_periods) : next;

                setData('logged_periods', resolved);
                patch({ periodTracking: { loggedPeriods: resolved } });
            }}
            onAverageCycleLengthChange={(value) => {
                handleAverageCycleLengthChange(value);
                patch({ periodTracking: { averageCycleLength: value } });
            }}
            onSubmit={() => {
                patch({
                    periodTracking: {
                        loggedPeriods: data.logged_periods,
                        averageCycleLength: data.average_cycle_length,
                    },
                });
                post(onboarding.urls?.periodTracking ?? '/onboarding/period-tracking');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'period_tracking'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

PeriodTracking.layout = customerOnboardingLayout;
