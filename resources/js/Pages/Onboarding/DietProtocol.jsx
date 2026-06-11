import { useEffect, useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import OnboardingInlineDescription from '../../Components/Molecules/Onboarding/OnboardingInlineDescription.jsx';
import OnboardingOptionButton from '../../Components/Molecules/Onboarding/OnboardingOptionButton.jsx';
import {
    dietProtocolOptionsForGender,
    shouldAutoAdvanceDietProtocol,
    shouldShowDietProtocolContinueButton,
} from '../../Components/Molecules/Onboarding/dietProtocolOptions.js';
import { resolveDietProtocol } from '../../Components/Molecules/Onboarding/dietProtocolUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import {
    dietProtocolToServer,
    normalizeDietProtocol,
} from '../../meal-craft/onboarding/onboardingNormalize.js';
import OnboardingStepFrame from '../../Components/Molecules/Onboarding/OnboardingStepFrame.jsx';

/**
 * Diet protocol onboarding step (Storybook / Inertia).
 *
 * @param {{
 *   protocol?: import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId | string | null;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onProtocolChange?: (value: import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId) => void;
 *   onProtocolSelect?: (value: import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   embedded?: boolean;
 *   gender?: import('../../meal-craft/onboarding/onboardingConstants.js').OnboardingGender | '';
 * }} props
 */
export function OnboardingDietProtocolInner({
    protocol: protocolProp,
    errors = {},
    processing = false,
    onProtocolChange,
    onProtocolSelect,
    onSubmit,
    steps = [],
    currentStep = 'diet_protocol',
    customerName = '',
    embedded = false,
    gender = '',
}) {
    const isControlled = onProtocolChange !== undefined;
    const initialProtocol = resolveDietProtocol(protocolProp);

    const [demoProtocol, setDemoProtocol] = useState(initialProtocol);
    const [pendingProtocol, setPendingProtocol] = useState('');

    const protocol = isControlled ? resolveDietProtocol(protocolProp) : demoProtocol;
    const visibleOptions = useMemo(() => dietProtocolOptionsForGender(gender), [gender]);
    const isAdvancing = processing || pendingProtocol !== '';

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

    useEffect(() => {
        if (!processing) {
            setPendingProtocol('');
        }
    }, [processing]);

    useEffect(() => {
        if (gender !== 'male' || protocol !== 'cycle_sync') {
            return;
        }

        if (isControlled) {
            onProtocolChange?.('balanced');
            return;
        }

        setDemoProtocol('balanced');
    }, [gender, protocol, isControlled, onProtocolChange]);

    const handleOptionSelect = (
        /** @type {import('../../Components/Molecules/Onboarding/dietProtocolOptions.js').DietProtocolId} */ optionId,
    ) => {
        if (isAdvancing) {
            return;
        }

        setProtocol(optionId);

        if (!onProtocolSelect || !shouldAutoAdvanceDietProtocol(optionId)) {
            return;
        }

        setPendingProtocol(optionId);
        onProtocolSelect(optionId);
    };

    const needsManualContinue = shouldShowDietProtocolContinueButton(protocol);
    const hideInnerNext = embedded || (Boolean(onProtocolSelect) && !needsManualContinue);

    return (
        <OnboardingStepFrame
            embedded={embedded}
            title="Diet protocol"
            description="Select the nutrition plan that best fits your needs."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <div className="flex w-full flex-col gap-5">
                <div
                    className="w-full"
                    role="group"
                    aria-label="Diet protocol options"
                    aria-busy={isAdvancing}
                >
                    {isAdvancing ? (
                        <p className="sr-only" role="status">
                            Saving your selection…
                        </p>
                    ) : null}
                    <div className="flex w-full flex-col gap-2.5">
                        {visibleOptions.map((option) => {
                            const selected = protocol === option.id || pendingProtocol === option.id;
                            const descriptionId = `diet-protocol-desc-${option.id}`;
                            const busy = pendingProtocol === option.id && isAdvancing;

                            return (
                                <div key={option.id} className="flex w-full flex-col">
                                    <OnboardingOptionButton
                                        label={option.label}
                                        selected={selected}
                                        disabled={isAdvancing}
                                        onSelect={() => handleOptionSelect(option.id)}
                                        icon={<option.Icon className={busy ? 'opacity-80' : ''} />}
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
                </div>

                {hideInnerNext ? null : (
                    <div className="pt-1">
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() => onSubmit?.()}
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
                )}
            </div>
        </OnboardingStepFrame>
    );
}

export default function DietProtocol() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch, computeTargetsBeforeSummary } = useOnboardingStore();
    const [submitting, setSubmitting] = useState(false);

    const { data, setData, processing, errors } = useForm({
        diet_protocol: resolveDietProtocol(
            state.dietProtocol || profile.diet_protocol || profile.dietProtocol,
        ),
    });

    const isBusy = processing || submitting;

    const submitDietProtocol = (normalized) => {
        setSubmitting(true);

        router.post(
            onboarding.urls?.dietProtocol ?? '/onboarding/diet-protocol',
            { diet_protocol: dietProtocolToServer(normalized) },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <OnboardingDietProtocolInner
            protocol={data.diet_protocol}
            errors={errors}
            processing={isBusy}
            gender={state.gender || profile.sex || profile.gender || ''}
            onProtocolChange={(value) => {
                setData('diet_protocol', value);
                patch({ dietProtocol: normalizeDietProtocol(value) });
            }}
            onProtocolSelect={(value) => {
                if (!shouldAutoAdvanceDietProtocol(value)) {
                    return;
                }

                const normalized = normalizeDietProtocol(value);
                setData('diet_protocol', normalized);
                patch({ dietProtocol: normalized });
                computeTargetsBeforeSummary();
                submitDietProtocol(normalized);
            }}
            onSubmit={() => {
                const normalized = normalizeDietProtocol(data.diet_protocol);
                setData('diet_protocol', normalized);
                patch({ dietProtocol: normalized });
                computeTargetsBeforeSummary();
                submitDietProtocol(normalized);
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'diet_protocol'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

DietProtocol.layout = customerOnboardingLayout;
