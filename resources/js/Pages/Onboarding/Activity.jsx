import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import OnboardingInlineDescription from '../../Components/Molecules/Onboarding/OnboardingInlineDescription.jsx';
import OnboardingOptionButton from '../../Components/Molecules/Onboarding/OnboardingOptionButton.jsx';
import {
    ACTIVITY_LEVEL_OPTIONS,
    defaultActivityLevel,
    resolveActivityLevel,
} from '../../Components/Molecules/Onboarding/activityUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.js';
import {
    activityLevelToServer,
    normalizeActivityLevel,
} from '../../meal-craft/onboarding/onboardingNormalize.js';
import { OnboardingShell } from './Welcome.jsx';

/**
 * @param {{
 *   activityLevel?: string;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onActivityLevelChange?: (value: string) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 * }} props
 */
export function OnboardingActivityInner({
    activityLevel: activityLevelProp,
    errors = {},
    processing = false,
    onActivityLevelChange,
    onSubmit,
    steps = [],
    currentStep = 'activity',
    customerName = '',
}) {
    const isControlled = onActivityLevelChange !== undefined;
    const initialLevel = resolveActivityLevel(activityLevelProp);

    const [demoActivityLevel, setDemoActivityLevel] = useState(initialLevel);

    const activityLevel = isControlled ? resolveActivityLevel(activityLevelProp) : demoActivityLevel;

    const setActivityLevel = (nextLevel) => {
        const resolved = resolveActivityLevel(nextLevel);

        if (isControlled) {
            onActivityLevelChange?.(resolved);
            return;
        }

        setDemoActivityLevel(resolved);
    };

    return (
        <OnboardingShell
            title="How active are you every day?"
            description="Your activity level influences how many calories you burn, allowing us to provide accurate daily nutrition targets."
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
                    <legend className="sr-only">Daily activity level</legend>
                    <div className="flex w-full flex-col gap-2.5" role="group" aria-label="Daily activity level">
                        {ACTIVITY_LEVEL_OPTIONS.map((option) => {
                            const selected = activityLevel === option.value;
                            const descriptionId = `activity-level-desc-${option.value}`;

                            return (
                                <div key={option.value} className="flex w-full flex-col">
                                    <OnboardingOptionButton
                                        label={option.label}
                                        selected={selected}
                                        onSelect={() => setActivityLevel(option.value)}
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
                    {errors.activity_level ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.activity_level}
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

export default function Activity() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        activity_level: resolveActivityLevel(
            state.activityLevel || profile.activityLevel || profile.activity_level || defaultActivityLevel(),
        ),
    });

    return (
        <OnboardingActivityInner
            activityLevel={data.activity_level}
            errors={errors}
            processing={processing}
            onActivityLevelChange={(value) => {
                const normalized = normalizeActivityLevel(value);
                setData('activity_level', normalized);
                patch({ activityLevel: normalized });
            }}
            onSubmit={() => {
                const normalized = normalizeActivityLevel(data.activity_level);
                patch({ activityLevel: normalized });
                post(onboarding.urls?.activity ?? '/onboarding/activity', {
                    activity_level: activityLevelToServer(normalized),
                });
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'activity'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Activity.layout = customerOnboardingLayout;
