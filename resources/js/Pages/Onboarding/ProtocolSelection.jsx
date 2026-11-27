import { useCallback, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import ProtocolModeSelector from '../../Components/Molecules/Onboarding/ProtocolModeSelector.jsx';
import OnboardingStepFrame from '../../Components/Molecules/Onboarding/OnboardingStepFrame.jsx';
import { DEFAULT_PROTOCOL_MODE } from '../../Components/Molecules/Onboarding/cycleSyncPhaseUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';

/**
 * @param {{
 *   protocolMode?: 'non_fasting' | 'fasting';
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onProtocolModeChange?: (value: 'non_fasting' | 'fasting') => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   embedded?: boolean;
 * }} props
 */
export function OnboardingProtocolSelectionInner({
    protocolMode: protocolModeProp,
    errors = {},
    processing = false,
    onProtocolModeChange,
    onSubmit,
    steps = [],
    currentStep = 'protocol_selection',
    customerName = '',
    embedded = false,
}) {
    const [demoMode, setDemoMode] = useState(
        protocolModeProp === 'fasting' ? 'fasting' : DEFAULT_PROTOCOL_MODE,
    );
    const isControlled = typeof onProtocolModeChange === 'function';
    const protocolMode = isControlled
        ? (protocolModeProp === 'fasting' ? 'fasting' : DEFAULT_PROTOCOL_MODE)
        : demoMode;
    const setProtocolMode = isControlled ? onProtocolModeChange : setDemoMode;

    return (
        <OnboardingStepFrame
            embedded={embedded}
            title="Include intermittent fasting?"
            description="You can change this anytime."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <form
                className="flex w-full flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();
                }}
            >
                <ProtocolModeSelector
                    value={protocolMode}
                    onChange={setProtocolMode}
                    disabled={processing}
                />

                {errors.protocol_mode ? (
                    <p className="text-center text-sm text-red-600" role="alert">
                        {errors.protocol_mode}
                    </p>
                ) : null}

                {embedded ? null : (
                    <div className="pt-1">
                        <Button
                            type="submit"
                            label={processing ? 'Saving…' : 'Continue'}
                            disabled={processing}
                            className="w-full uppercase tracking-[0.08em]"
                        />
                    </div>
                )}
            </form>
        </OnboardingStepFrame>
    );
}

export default function ProtocolSelection() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        protocol_mode:
            state.periodTracking.protocolMode ??
            profile.protocol_mode ??
            profile.protocolMode ??
            DEFAULT_PROTOCOL_MODE,
    });

    const handleProtocolModeChange = useCallback(
        (value) => {
            setData('protocol_mode', value);
        },
        [setData],
    );

    return (
        <OnboardingProtocolSelectionInner
            protocolMode={data.protocol_mode}
            errors={errors}
            processing={processing}
            onProtocolModeChange={handleProtocolModeChange}
            onSubmit={() => {
                patch({
                    periodTracking: {
                        protocolMode: data.protocol_mode,
                    },
                });
                post(onboarding.urls?.protocolSelection ?? '/onboarding/protocol-selection');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'protocol_selection'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

ProtocolSelection.layout = customerOnboardingLayout;
