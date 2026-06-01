import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import OnboardingInlineDescription from '../../Components/Molecules/Onboarding/OnboardingInlineDescription.jsx';
import OnboardingOptionButton from '../../Components/Molecules/Onboarding/OnboardingOptionButton.jsx';
import { DIET_PROTOCOL_OPTIONS } from '../../Components/Molecules/Onboarding/dietProtocolOptions.js';
import { resolveDietProtocol } from '../../Components/Molecules/Onboarding/dietProtocolUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.js';
import {
    dietProtocolToServer,
    normalizeDietProtocol,
} from '../../meal-craft/onboarding/onboardingNormalize.js';
import { OnboardingShell } from './Welcome.jsx';

/**
 * Diet protocol onboarding step (Storybook / Inertia).
 *
 * @param {{
 *   protocol?: import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId | string | null;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onProtocolChange?: (value: import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 * }} props
 */
export function OnboardingDietProtocolInner({
    protocol: protocolProp,
    errors = {},
    processing = false,
    onProtocolChange,
    onSubmit,
    steps = [],
    currentStep = 'diet_protocol',
    customerName = '',
}) {
    const isControlled = onProtocolChange !== undefined;
    const initialProtocol = resolveDietProtocol(protocolProp);

    const [demoProtocol, setDemoProtocol] = useState(initialProtocol);

    const protocol = isControlled ? resolveDietProtocol(protocolProp) : demoProtocol;

    const setProtocol = (
        /** @type {import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId} */ next,
    ) => {
        const resolved = resolveDietProtocol(next);

        if (isControlled) {
            onProtocolChange?.(resolved);
            return;
        }

        setDemoProtocol(resolved);
    };

    return (
        <OnboardingShell
            title="Diet protocol"
            description="Select the nutrition plan that best fits your needs."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <form
                className="flex w-full flex-col gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();
                }}
            >
                <fieldset className="w-full min-w-0 border-0 p-0">
                    <legend className="sr-only">Diet protocol</legend>
                    <div className="flex w-full flex-col gap-2.5" role="group" aria-label="Diet protocol options">
                        {DIET_PROTOCOL_OPTIONS.map((option) => {
                            const selected = protocol === option.id;
                            const descriptionId = `diet-protocol-desc-${option.id}`;

                            return (
                                <div key={option.id} className="flex w-full flex-col">
                                    <OnboardingOptionButton
                                        label={option.label}
                                        selected={selected}
                                        onSelect={() => setProtocol(option.id)}
                                        icon={<option.Icon />}
                                        describedBy={descriptionId}
                                    />
                                    <div
                                        className={[
                                            'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                                            selected ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                        ].join(' ')}
                                        aria-hidden={!selected}
                                    >
                                        <div className="min-h-0 overflow-hidden">
                                            <OnboardingInlineDescription id={descriptionId}>
                                                {option.description}
                                            </OnboardingInlineDescription>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {errors.diet_protocol ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.diet_protocol}
                        </p>
                    ) : null}
                </fieldset>

                <div className="pt-1">
                    <button
                        type="submit"
                        disabled={processing}
                        className={[
                            'inline-flex h-[50px] w-full min-h-[50px] items-center justify-center rounded-[12px]',
                            'font-montserrat text-[16px] font-bold uppercase leading-none tracking-[0.08em] text-white',
                            'transition-all duration-200 ease-in-out',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-protocol-selected focus-visible:ring-offset-2',
                            processing
                                ? 'cursor-not-allowed border-2 border-protocol-selected/40 bg-protocol-selected/40'
                                : 'border-2 border-protocol-selected bg-protocol-selected hover:border-protocol-selected-hover hover:bg-protocol-selected-hover active:border-protocol-selected-pressed active:bg-protocol-selected-pressed',
                        ].join(' ')}
                    >
                        {processing ? 'Saving…' : 'Next'}
                    </button>
                </div>
            </form>
        </OnboardingShell>
    );
}

export default function DietProtocol() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch, computeTargetsBeforeSummary } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        diet_protocol: resolveDietProtocol(
            state.dietProtocol || profile.diet_protocol || profile.dietProtocol,
        ),
    });

    return (
        <OnboardingDietProtocolInner
            protocol={data.diet_protocol}
            errors={errors}
            processing={processing}
            onProtocolChange={(value) => {
                setData('diet_protocol', value);
                patch({ dietProtocol: normalizeDietProtocol(value) });
            }}
            onSubmit={() => {
                const normalized = normalizeDietProtocol(data.diet_protocol);
                patch({ dietProtocol: normalized });
                computeTargetsBeforeSummary();
                post(onboarding.urls?.dietProtocol ?? '/onboarding/diet-protocol', {
                    diet_protocol: dietProtocolToServer(normalized),
                });
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'diet_protocol'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

DietProtocol.layout = customerOnboardingLayout;
