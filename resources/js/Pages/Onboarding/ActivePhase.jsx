import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import ActivePhaseCard from '../../Components/Molecules/Onboarding/ActivePhaseCard.jsx';
import OnboardingStepFrame from '../../Components/Molecules/Onboarding/OnboardingStepFrame.jsx';
import {
    buildCycleSyncCalendarState,
    DEFAULT_PROTOCOL_MODE,
} from '../../Components/Molecules/Onboarding/cycleSyncPhaseUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';

/**
 * @param {{
 *   activePhase?: {
 *     protocolMode?: string;
 *     cycleDay?: number | null;
 *     cycleLength?: number | null;
 *     currentSyncPhase?: string | null;
 *     currentPhaseMetadata?: Record<string, unknown> | null;
 *     mealPlanGenerationPayload?: Record<string, unknown> | null;
 *     loggedPeriods?: Array<{ start: string; end?: string | null }>;
 *   } | null;
 *   protocolMode?: string;
 *   loggedPeriods?: Array<{ start: string; end?: string | null }>;
 *   averageCycleLength?: number | null;
 *   protocolSelectionUrl?: string;
 *   craftedForYouUrl?: string;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   embedded?: boolean;
 * }} props
 */
export function OnboardingActivePhaseInner({
    activePhase = null,
    protocolMode: protocolModeProp,
    loggedPeriods = [],
    averageCycleLength = null,
    protocolSelectionUrl = '/onboarding/protocol_selection',
    craftedForYouUrl = '/consultation/crafted-for-you',
    processing = false,
    onSubmit,
    steps = [],
    currentStep = 'active_phase',
    customerName = '',
    embedded = false,
}) {
    const protocolMode =
        activePhase?.protocolMode ??
        (protocolModeProp === 'fasting' ? 'fasting' : DEFAULT_PROTOCOL_MODE);

    const localState =
        activePhase?.currentSyncPhase != null
            ? null
            : buildCycleSyncCalendarState(
                  loggedPeriods,
                  averageCycleLength ?? undefined,
                  new Date(),
                  protocolMode,
              );

    const phaseId = activePhase?.currentSyncPhase ?? localState?.currentPhase ?? null;
    const cycleDay = activePhase?.cycleDay ?? localState?.cycleDay ?? null;
    const cycleLength = activePhase?.cycleLength ?? localState?.cycleLength ?? null;
    const metadata = activePhase?.currentPhaseMetadata ?? localState?.currentPhaseMetadata ?? null;
    const mealPlanPayload =
        activePhase?.mealPlanGenerationPayload ?? localState?.mealPlanGenerationPayload ?? null;

    return (
        <OnboardingStepFrame
            embedded={embedded}
            title="Your active cycle phase"
            description="Food approach and guidance adapt to where you are in your cycle."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
            titleClassName="text-brand-primary-pressed"
        >
            <div className="flex w-full flex-col gap-4 md:mx-auto md:max-w-xl">
                <ActivePhaseCard
                    phaseId={phaseId}
                    protocolMode={protocolMode}
                    cycleDay={cycleDay}
                    cycleLength={cycleLength}
                    metadata={metadata}
                    mealPlanPayload={mealPlanPayload}
                    craftedForYouUrl={craftedForYouUrl}
                    showProtocolSelector={false}
                    changeProtocolUrl={protocolSelectionUrl}
                    processing={processing}
                />

                {embedded ? null : (
                    <div className="flex w-full justify-center">
                        <Button
                            type="button"
                            label={processing ? 'Saving…' : 'Continue'}
                            disabled={processing}
                            onClick={onSubmit}
                            className="min-w-[200px] uppercase tracking-[0.08em]"
                        />
                    </div>
                )}
            </div>
        </OnboardingStepFrame>
    );
}

export default function ActivePhase(props) {
    const page = usePage();
    const onboarding = onboardingFromPage(page.props);
    const profile = onboarding.profile ?? {};
    const { state } = useOnboardingStore();
    const activePhase = props.activePhase ?? page.props.activePhase ?? null;
    const { post, processing } = useForm({});

    return (
        <OnboardingActivePhaseInner
            activePhase={activePhase}
            protocolMode={
                state.periodTracking.protocolMode ??
                profile.protocol_mode ??
                profile.protocolMode ??
                DEFAULT_PROTOCOL_MODE
            }
            loggedPeriods={
                state.periodTracking.loggedPeriods.length
                    ? state.periodTracking.loggedPeriods
                    : (profile.logged_periods ?? profile.loggedPeriods ?? [])
            }
            averageCycleLength={
                state.periodTracking.averageCycleLength ??
                profile.average_cycle_length ??
                profile.averageCycleLength
            }
            protocolSelectionUrl={
                props.protocolSelectionUrl ??
                page.props.protocolSelectionUrl ??
                '/onboarding/protocol_selection'
            }
            craftedForYouUrl={
                props.craftedForYouUrl ?? page.props.craftedForYouUrl ?? '/consultation/crafted-for-you'
            }
            processing={processing}
            onSubmit={() => {
                post(onboarding.urls?.activePhase ?? '/onboarding/active-phase');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'active_phase'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

ActivePhase.layout = customerOnboardingLayout;
