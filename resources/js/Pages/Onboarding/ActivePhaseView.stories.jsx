import { OnboardingActivePhaseInner } from './ActivePhase.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';
import { ONBOARDING_STEPS } from './onboardingSteps.js';
import { phaseGuidanceFor, mealPlanGenerationPayloadFor } from '../../Components/Molecules/Onboarding/cycleSyncPhaseUtils.js';

export default {
    title: 'Design System/06. Pages/Onboarding/ActivePhaseView',
    component: OnboardingActivePhaseInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Screen 3 after Protocol Selection: active phase, why copy for the chosen protocol_mode, meal-plan CTA, and change-protocol chip.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

const loggedPeriods = [{ start: '2026-04-20', end: '2026-04-22' }];

export const NonFastingPowerPhase = {
    name: 'Non-fasting · Power Phase',
    render: () => (
        <OnboardingActivePhaseInner
            protocolMode="non_fasting"
            loggedPeriods={loggedPeriods}
            averageCycleLength={30}
            activePhase={{
                protocolMode: 'non_fasting',
                cycleDay: 6,
                cycleLength: 30,
                currentSyncPhase: 'power_phase_1',
                currentPhaseMetadata: phaseGuidanceFor('power_phase_1', 'non_fasting'),
                mealPlanGenerationPayload: mealPlanGenerationPayloadFor('power_phase_1', 'non_fasting'),
            }}
            protocolSelectionUrl="#protocol-selection"
            craftedForYouUrl="#crafted"
            steps={ONBOARDING_STEPS}
            currentStep="active_phase"
            customerName="Amina Saif"
            onSubmit={() => undefined}
        />
    ),
};

export const FastingNurturePhase = {
    name: 'Fasting mode · Nurture stays no fasting',
    render: () => (
        <OnboardingActivePhaseInner
            protocolMode="fasting"
            loggedPeriods={loggedPeriods}
            averageCycleLength={30}
            activePhase={{
                protocolMode: 'fasting',
                cycleDay: 25,
                cycleLength: 30,
                currentSyncPhase: 'nurture_phase',
                currentPhaseMetadata: phaseGuidanceFor('nurture_phase', 'fasting'),
                mealPlanGenerationPayload: mealPlanGenerationPayloadFor('nurture_phase', 'fasting'),
            }}
            protocolSelectionUrl="#protocol-selection"
            craftedForYouUrl="#crafted"
            steps={ONBOARDING_STEPS}
            currentStep="active_phase"
            customerName="Amina Saif"
            onSubmit={() => undefined}
        />
    ),
};
