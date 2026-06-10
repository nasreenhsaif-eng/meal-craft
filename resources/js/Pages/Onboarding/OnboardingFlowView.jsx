import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useMemo, useReducer } from 'react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { defaultHeightCm } from '../../Components/Molecules/Onboarding/heightUtils.js';
import { defaultBirthdayValue, toIsoDate } from '../../Components/Molecules/Onboarding/wheelDateUtils.js';
import { defaultWeightKg } from '../../Components/Molecules/Onboarding/weightUtils.js';
import { calculateDailyTargets } from '../../meal-craft/dailyTargetsCalculator.js';
import {
    advanceMaleOnboardingStep,
    listMaleOnboardingSteps,
    MALE_ONBOARDING_START_STEP,
    retreatMaleOnboardingStep,
} from './onboardingMaleFlow.js';
import {
    createInitialOnboardingState,
    onboardingStateToProfile,
    patchOnboardingState,
} from '../../meal-craft/onboarding/onboardingState.js';
import { OnboardingActivityInner } from './Activity.jsx';
import { OnboardingBirthdayInner } from './Birthday.jsx';
import { DailyTargetsSummaryInner } from './DailyTargetsSummary.jsx';
import { OnboardingDietProtocolInner } from './DietProtocol.jsx';
import { OnboardingFoodFilterInner } from './FoodFilter.jsx';
import { OnboardingGenderInner } from './Gender.jsx';
import { OnboardingHeightInner } from './Height.jsx';
import { OnboardingTargetWeightInner } from './TargetWeight.jsx';
import { OnboardingWeightInner } from './Weight.jsx';
import { ONBOARDING_STEP_META } from './onboardingStepMeta.js';
import { onboardingSteps } from './onboardingSteps.js';
import { OnboardingShell } from './Welcome.jsx';

const SEX_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const STEP_TRANSITION = {
    initial: { opacity: 0 },
    animate: { opacity: 1 },
    exit: { opacity: 0 },
    transition: { duration: 0.2, ease: 'easeInOut' },
};

/**
 * @param {import('../../meal-craft/onboarding/onboardingConstants.js').OnboardingStepId} step
 * @returns {string}
 */
function flowFooterLabel(step) {
    if (step === 'daily_targets') {
        return 'Craft my plan';
    }

    if (step === 'food_filters') {
        return 'Confirm';
    }

    return 'Continue';
}

/**
 * @param {import('../../meal-craft/onboarding/onboardingState.js').OnboardingWizardState | undefined} initialState
 * @returns {import('../../meal-craft/onboarding/onboardingState.js').OnboardingWizardState}
 */
export function createStorybookOnboardingState(initialState) {
    const base = createInitialOnboardingState();

    return patchOnboardingState(base, {
        gender: 'male',
        birthdate: toIsoDate(defaultBirthdayValue()),
        height: defaultHeightCm(),
        weight: defaultWeightKg(),
        targetWeight: defaultWeightKg(),
        ...initialState,
    });
}

/**
 * @param {import('../../meal-craft/onboarding/onboardingState.js').OnboardingWizardState} state
 * @param {{ type: string; payload?: object }} action
 */
function flowReducer(state, action) {
    if (action.type === 'PATCH') {
        return patchOnboardingState(state, /** @type {object} */ (action.payload));
    }

    if (action.type === 'SET_TARGETS') {
        return { ...state, computedTargets: /** @type {object} */ (action.payload) };
    }

    return state;
}

/**
 * @param {{
 *   customerName?: string;
 *   initialStep?: import('../../meal-craft/onboarding/onboardingConstants.js').OnboardingStepId;
 *   initialState?: Partial<import('../../meal-craft/onboarding/onboardingState.js').OnboardingWizardState>;
 *   showFlowChrome?: boolean;
 *   onFlowComplete?: () => void;
 * }} props
 */
export function OnboardingFlowViewInner({
    customerName = 'James Okonkwo',
    initialStep = MALE_ONBOARDING_START_STEP,
    initialState,
    showFlowChrome = true,
    onFlowComplete,
}) {
    const steps = useMemo(() => onboardingSteps({ includePeriodTracking: false }), []);
    const maleStepIds = useMemo(() => listMaleOnboardingSteps(), []);

    const [wizardState, dispatch] = useReducer(
        flowReducer,
        undefined,
        () => createStorybookOnboardingState(initialState),
    );

    const [currentStep, setCurrentStep] = useReducer(
        (_prev, next) => next,
        initialStep ?? MALE_ONBOARDING_START_STEP,
    );

    const flowContext = useMemo(
        () => ({ gender: wizardState.gender || 'male' }),
        [wizardState.gender],
    );

    const patch = useCallback((payload) => {
        dispatch({ type: 'PATCH', payload });
    }, []);

    const computeTargets = useCallback(() => {
        const targets = calculateDailyTargets(onboardingStateToProfile(wizardState));

        dispatch({ type: 'SET_TARGETS', payload: targets });

        return targets;
    }, [wizardState]);

    const onNext = useCallback(() => {
        if (currentStep === 'diet_protocol') {
            computeTargets();
        }

        const next = advanceMaleOnboardingStep(currentStep, flowContext);

        if (next) {
            setCurrentStep(next);
            patch({ currentStep: next });

            return;
        }

        onFlowComplete?.();
    }, [currentStep, flowContext, computeTargets, onFlowComplete, patch]);

    const onDietProtocolSelect = useCallback(
        (value) => {
            if (value !== 'balanced') {
                return;
            }

            patch({ dietProtocol: value });
            computeTargets();

            const next = advanceMaleOnboardingStep('diet_protocol', flowContext);

            if (next) {
                setCurrentStep(next);
                patch({ currentStep: next });
            }
        },
        [patch, computeTargets, flowContext],
    );

    const onGenderSelect = useCallback(
        (value) => {
            patch({ gender: value });

            const next = advanceMaleOnboardingStep('gender', { gender: value });

            if (next) {
                setCurrentStep(next);
                patch({ currentStep: next });
            }
        },
        [patch],
    );

    const onBack = useCallback(() => {
        const previous = retreatMaleOnboardingStep(currentStep, flowContext);

        if (previous) {
            setCurrentStep(previous);
            patch({ currentStep: previous });
        }
    }, [currentStep, flowContext, patch]);

    const targets =
        wizardState.computedTargets ?? calculateDailyTargets(onboardingStateToProfile(wizardState));

    const meta = ONBOARDING_STEP_META[currentStep] ?? ONBOARDING_STEP_META.gender;

    const flowSteps = useMemo(
        () =>
            maleStepIds.map(
                (id) => steps.find((step) => step.value === id) ?? { value: id, label: id },
            ),
        [maleStepIds, steps],
    );

    const shared = {
        embedded: true,
        steps,
        currentStep,
        customerName,
        processing: false,
        onSubmit: onNext,
    };

    const stepView = (() => {
        switch (currentStep) {
            case 'gender':
                return (
                    <OnboardingGenderInner
                        {...shared}
                        sex={wizardState.gender}
                        options={SEX_OPTIONS}
                        onSexSelect={onGenderSelect}
                    />
                );
            case 'birthday':
                return (
                    <OnboardingBirthdayInner
                        {...shared}
                        dateOfBirth={wizardState.birthdate}
                        onDateChange={(iso) => patch({ birthdate: iso })}
                    />
                );
            case 'height':
                return (
                    <OnboardingHeightInner
                        {...shared}
                        heightCm={wizardState.height ?? undefined}
                        onHeightCmChange={(value) => patch({ height: value })}
                    />
                );
            case 'weight':
                return (
                    <OnboardingWeightInner
                        {...shared}
                        weightKg={wizardState.weight ?? undefined}
                        onWeightKgChange={(value) => patch({ weight: value })}
                    />
                );
            case 'target_weight':
                return (
                    <OnboardingTargetWeightInner
                        {...shared}
                        weightKg={wizardState.targetWeight ?? wizardState.weight ?? undefined}
                        onWeightKgChange={(value) => patch({ targetWeight: value })}
                    />
                );
            case 'activity':
                return (
                    <OnboardingActivityInner
                        {...shared}
                        activityLevel={wizardState.activityLevel}
                        onActivityLevelChange={(value) => patch({ activityLevel: value })}
                    />
                );
            case 'diet_protocol':
                return (
                    <OnboardingDietProtocolInner
                        {...shared}
                        protocol={wizardState.dietProtocol}
                        gender={wizardState.gender}
                        onProtocolChange={(value) => patch({ dietProtocol: value })}
                        onProtocolSelect={onDietProtocolSelect}
                    />
                );
            case 'daily_targets':
                return (
                    <DailyTargetsSummaryInner
                        {...shared}
                        targets={targets}
                        hideDefaultHeader
                        onStartPlan={onNext}
                    />
                );
            case 'food_filters':
                return (
                    <OnboardingFoodFilterInner
                        {...shared}
                        selectedFilters={wizardState.foodFilters}
                        otherText={wizardState.allergyOther}
                        onSelectedFiltersChange={(value) => patch({ foodFilters: value })}
                        onOtherTextChange={(value) => patch({ allergyOther: value })}
                    />
                );
            default:
                return (
                    <p className="p-8 text-center text-sm text-red-600" role="alert">
                        Unknown onboarding step: {currentStep}
                    </p>
                );
        }
    })();

    return (
        <OnboardingShell
            title={meta.title}
            description={meta.description}
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader={meta.centerHeader}
            hideDefaultHeader={meta.hideDefaultHeader === true}
            titleClassName={meta.titleClassName ?? ''}
            visibleSteps={flowSteps}
            onBack={showFlowChrome ? onBack : undefined}
        >
            <div className="relative min-h-0">
                <AnimatePresence mode="wait" initial={false}>
                    <motion.div key={currentStep} className="min-h-0" {...STEP_TRANSITION}>
                        {stepView}
                    </motion.div>
                </AnimatePresence>
            </div>

            {meta.hideNext ? null : (
                <div className="mt-6 flex w-full justify-center sm:mt-10">
                    <Button
                        type="button"
                        label={flowFooterLabel(currentStep)}
                        onClick={onNext}
                        className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em] sm:w-auto"
                    />
                </div>
            )}
        </OnboardingShell>
    );
}

export default OnboardingFlowViewInner;
