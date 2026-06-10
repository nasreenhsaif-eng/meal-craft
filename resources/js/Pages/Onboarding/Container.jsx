import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { calculateDailyTargets } from '../../meal-craft/dailyTargetsCalculator.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { buildOnboardingStepPayload, resolveOnboardingStepPostUrl } from '../../meal-craft/onboarding/buildOnboardingStepPayload.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import {
    getNextTabStep,
    getPreviousTabStep,
    getVisibleOnboardingSteps,
} from '../../meal-craft/onboarding/onboardingTabFlow.js';
import { validateOnboardingStep } from '../../meal-craft/onboarding/validateOnboardingStep.js';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import { OnboardingActivityInner } from './Activity.jsx';
import { OnboardingBirthdayInner } from './Birthday.jsx';
import { DailyTargetsSummaryInner } from './DailyTargetsSummary.jsx';
import { OnboardingDietProtocolInner } from './DietProtocol.jsx';
import { OnboardingFoodFilterInner } from './FoodFilter.jsx';
import { OnboardingGenderInner } from './Gender.jsx';
import { OnboardingHeightInner } from './Height.jsx';
import { OnboardingPeriodTrackingInner } from './PeriodTracking.jsx';
import { OnboardingTargetWeightInner } from './TargetWeight.jsx';
import { OnboardingWeightInner } from './Weight.jsx';
import { ONBOARDING_STEP_META } from './onboardingStepMeta.js';
import { OnboardingShell } from './Welcome.jsx';

/**
 * @param {string} step
 * @returns {string}
 */
function nextButtonLabel(step) {
    if (step === 'daily_targets') {
        return 'Craft my plan';
    }

    if (step === 'food_filters') {
        return 'Confirm';
    }

    return 'Next';
}

/**
 * Tabbed customer onboarding — all steps stay mounted so local state is preserved when switching tabs.
 */
export default function Container() {
    const pageProps = usePage().props;
    const onboarding = onboardingFromPage(pageProps);
    const activeStep = pageProps.activeStep ?? 'gender';
    const { state, patch, computedTargets, profileInput, computeTargetsBeforeSummary } = useOnboardingStore();
    const [processing, setProcessing] = useState(false);
    const [validationErrors, setValidationErrors] = useState(/** @type {Record<string, string>} */ ({}));

    const steps = onboarding.steps ?? [];
    const visibleSteps = useMemo(
        () => getVisibleOnboardingSteps(steps, { gender: state.gender, dietProtocol: state.dietProtocol }),
        [steps, state.gender, state.dietProtocol],
    );
    const meta = ONBOARDING_STEP_META[activeStep] ?? ONBOARDING_STEP_META.gender;
    const options = onboarding.options ?? {};

    const targets = useMemo(
        () => computedTargets ?? calculateDailyTargets(profileInput),
        [computedTargets, profileInput],
    );

    const visitStep = useCallback(
        (step) => {
            if (step === activeStep) {
                return;
            }

            router.visit(`/onboarding/${step}`, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        [activeStep],
    );

    const handleBack = useCallback(() => {
        const previous = getPreviousTabStep(activeStep, visibleSteps);

        if (previous) {
            visitStep(previous);
        }
    }, [activeStep, visibleSteps, visitStep]);

    const handleNext = useCallback(() => {
        const { valid, errors } = validateOnboardingStep(activeStep, state);

        if (!valid) {
            setValidationErrors(errors);

            return;
        }

        setValidationErrors({});

        const urls = onboarding.urls ?? {};
        const postUrl = resolveOnboardingStepPostUrl(activeStep, urls);

        if (!postUrl) {
            return;
        }

        if (activeStep === 'diet_protocol') {
            computeTargetsBeforeSummary();
        }

        const payload = buildOnboardingStepPayload(activeStep, state);

        setProcessing(true);

        router.post(postUrl, payload, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                const next = getNextTabStep(activeStep, visibleSteps);

                if (next) {
                    patch({ currentStep: next });
                }
            },
        });
    }, [
        activeStep,
        state,
        onboarding.urls,
        computeTargetsBeforeSummary,
        visibleSteps,
        patch,
    ]);

    const handleGenderSelect = useCallback(
        (value) => {
            if (processing) {
                return;
            }

            patch({ gender: value });
            setValidationErrors({});

            const urls = onboarding.urls ?? {};
            const postUrl = resolveOnboardingStepPostUrl('gender', urls);

            if (!postUrl) {
                return;
            }

            const nextVisibleSteps = getVisibleOnboardingSteps(steps, { gender: value });

            setProcessing(true);

            router.post(
                postUrl,
                { sex: value },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onFinish: () => setProcessing(false),
                    onSuccess: () => {
                        const next = getNextTabStep('gender', nextVisibleSteps);

                        if (next) {
                            patch({ currentStep: next });
                        }
                    },
                },
            );
        },
        [processing, patch, onboarding.urls, steps],
    );

    const handleDietProtocolSelect = useCallback(
        (value) => {
            if (processing || value !== 'balanced') {
                return;
            }

            patch({ dietProtocol: value });
            setValidationErrors({});
            computeTargetsBeforeSummary();

            const urls = onboarding.urls ?? {};
            const postUrl = resolveOnboardingStepPostUrl('diet_protocol', urls);

            if (!postUrl) {
                return;
            }

            const payload = buildOnboardingStepPayload('diet_protocol', {
                ...state,
                dietProtocol: value,
            });

            setProcessing(true);

            router.post(postUrl, payload, {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    const nextVisibleSteps = getVisibleOnboardingSteps(steps, {
                        gender: state.gender,
                        dietProtocol: value,
                    });
                    const next = getNextTabStep('diet_protocol', nextVisibleSteps);

                    if (next) {
                        patch({ currentStep: next });
                    }
                },
            });
        },
        [
            processing,
            patch,
            onboarding.urls,
            computeTargetsBeforeSummary,
            state,
            steps,
        ],
    );

    const sharedInner = {
        embedded: true,
        steps,
        currentStep: activeStep,
        customerName: onboarding.customerName ?? '',
        processing,
        errors: validationErrors,
        onSubmit: handleNext,
    };

    const stepPanels = {
        gender: (
            <OnboardingGenderInner
                {...sharedInner}
                sex={state.gender}
                options={options.sex ?? []}
                onSexSelect={handleGenderSelect}
            />
        ),
        period_tracking: (
            <OnboardingPeriodTrackingInner
                {...sharedInner}
                loggedPeriods={state.periodTracking.loggedPeriods}
                averageCycleLength={state.periodTracking.averageCycleLength ?? undefined}
                onLoggedPeriodsChange={(value) => {
                    const resolved =
                        typeof value === 'function'
                            ? value(state.periodTracking.loggedPeriods)
                            : value;

                    patch({ periodTracking: { loggedPeriods: resolved } });
                }}
                onAverageCycleLengthChange={(value) =>
                    patch({ periodTracking: { averageCycleLength: value } })
                }
            />
        ),
        birthday: (
            <OnboardingBirthdayInner
                {...sharedInner}
                dateOfBirth={state.birthdate}
                onDateChange={(iso) => patch({ birthdate: iso })}
            />
        ),
        height: (
            <OnboardingHeightInner
                {...sharedInner}
                heightCm={state.height ?? undefined}
                onHeightCmChange={(value) => patch({ height: value })}
            />
        ),
        weight: (
            <OnboardingWeightInner
                {...sharedInner}
                weightKg={state.weight ?? undefined}
                onWeightKgChange={(value) => patch({ weight: value })}
            />
        ),
        target_weight: (
            <OnboardingTargetWeightInner
                {...sharedInner}
                weightKg={state.targetWeight ?? state.weight ?? undefined}
                onWeightKgChange={(value) => patch({ targetWeight: value })}
            />
        ),
        activity: (
            <OnboardingActivityInner
                {...sharedInner}
                activityLevel={state.activityLevel}
                onActivityLevelChange={(value) => patch({ activityLevel: value })}
            />
        ),
        diet_protocol: (
            <OnboardingDietProtocolInner
                {...sharedInner}
                protocol={state.dietProtocol}
                gender={state.gender}
                onProtocolChange={(value) => patch({ dietProtocol: value })}
                onProtocolSelect={handleDietProtocolSelect}
            />
        ),
        daily_targets: (
            <DailyTargetsSummaryInner
                {...sharedInner}
                targets={targets}
                hideDefaultHeader={true}
                onStartPlan={handleNext}
            />
        ),
        food_filters: (
            <OnboardingFoodFilterInner
                {...sharedInner}
                selectedFilters={state.foodFilters}
                otherText={state.allergyOther}
                onSelectedFiltersChange={(value) => patch({ foodFilters: value })}
                onOtherTextChange={(value) => patch({ allergyOther: value })}
            />
        ),
    };

    const hideFooterNext = meta.hideNext === true;

    return (
        <OnboardingShell
            title={meta.title}
            description={meta.description}
            steps={steps}
            currentStep={activeStep}
            customerName={onboarding.customerName ?? ''}
            centerHeader={meta.centerHeader}
            hideDefaultHeader={meta.hideDefaultHeader === true}
            titleClassName={meta.titleClassName ?? ''}
            visibleSteps={visibleSteps}
            onBack={handleBack}
        >
            <div className="relative min-h-[200px]">
                {visibleSteps.map((step) => (
                    <div
                        key={step.value}
                        hidden={step.value !== activeStep}
                        className={step.value === activeStep ? 'block' : 'hidden'}
                        aria-hidden={step.value !== activeStep}
                    >
                        {stepPanels[step.value] ?? null}
                    </div>
                ))}
            </div>

            {hideFooterNext ? null : (
                <div className="mt-6 flex w-full justify-center sm:mt-10">
                    <Button
                        type="button"
                        label={processing ? 'Saving…' : nextButtonLabel(activeStep)}
                        disabled={processing}
                        onClick={handleNext}
                        className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em] sm:w-auto"
                    />
                </div>
            )}
        </OnboardingShell>
    );
}

Container.layout = customerOnboardingLayout;
