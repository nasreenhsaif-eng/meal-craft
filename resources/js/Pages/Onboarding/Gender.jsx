import { useEffect, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { GenderOptionCard, genderOptionIcon } from '../../Components/Molecules/Onboarding/GenderOptionCard.jsx';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import OnboardingStepFrame from '../../Components/Molecules/Onboarding/OnboardingStepFrame.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';

/**
 * Gender profile step markup (Storybook / Inertia).
 *
 * @param {{
 *   sex?: string;
 *   options?: Array<{ value: string; label: string }>;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onSexChange?: (value: string) => void;
 *   onSexSelect?: (value: string) => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   embedded?: boolean;
 * }} props
 */
export function OnboardingGenderInner({
    sex: sexProp,
    options: optionsProp,
    errors = {},
    processing = false,
    onSexChange,
    onSexSelect,
    steps = [],
    currentStep = 'gender',
    customerName = '',
    embedded = false,
}) {
    const [demoSex, setDemoSex] = useState('');
    const [pendingValue, setPendingValue] = useState('');
    const sex = sexProp ?? demoSex;
    const handleSexChange = onSexChange ?? setDemoSex;
    const options = optionsProp?.length
        ? optionsProp
        : [
              { value: 'male', label: 'Male' },
              { value: 'female', label: 'Female' },
          ];
    const isAdvancing = processing || pendingValue !== '';

    useEffect(() => {
        if (!processing) {
            setPendingValue('');
        }
    }, [processing]);

    const handleOptionSelect = (value) => {
        if (isAdvancing) {
            return;
        }

        if (onSexSelect) {
            setPendingValue(value);
            onSexSelect(value);
            return;
        }

        handleSexChange(value);
    };

    return (
        <OnboardingStepFrame
            embedded={embedded}
            title="Create your profile"
            description="Select your gender so we can personalize calorie and macro calculations."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <div className="w-full">
                <div
                    className="w-full"
                    role="group"
                    aria-label="Gender options"
                    aria-busy={isAdvancing}
                >
                    {isAdvancing ? (
                        <p className="sr-only" role="status">
                            Saving your selection…
                        </p>
                    ) : null}
                    <div className="flex w-full flex-col gap-3 [&_.mc-gender-option]:w-full">
                        {options.map((option) => {
                            const selected = sex === option.value || pendingValue === option.value;
                            const busy = pendingValue === option.value && isAdvancing;

                            return (
                                <GenderOptionCard
                                    key={option.value}
                                    label={option.label}
                                    selected={selected}
                                    disabled={isAdvancing}
                                    busy={busy}
                                    onSelect={() => handleOptionSelect(option.value)}
                                    icon={genderOptionIcon(option.value)}
                                />
                            );
                        })}
                    </div>
                    {errors.sex ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.sex}
                        </p>
                    ) : null}
                </div>
            </div>
        </OnboardingStepFrame>
    );
}

export default function Gender() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const options = onboarding.options ?? {};
    const { state, patch } = useOnboardingStore();
    const [submitting, setSubmitting] = useState(false);

    const { data, setData, processing, errors } = useForm({
        sex: state.gender || profile.sex || '',
    });

    const isBusy = processing || submitting;

    return (
        <OnboardingGenderInner
            sex={data.sex}
            options={options.sex?.length ? options.sex : undefined}
            errors={errors}
            processing={isBusy}
            onSexSelect={(value) => {
                setData('sex', value);
                patch({ gender: value });
                setSubmitting(true);

                router.post(onboarding.urls?.gender ?? '/onboarding/gender', { sex: value }, {
                    preserveScroll: true,
                    onFinish: () => setSubmitting(false),
                });
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'gender'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Gender.layout = customerOnboardingLayout;
