import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { GenderOptionButton, genderOptionIcon } from '../../Components/Atoms/Button/GenderOptionButton.jsx';
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
 *   onSubmit?: () => void;
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
    onSubmit,
    steps = [],
    currentStep = 'gender',
    customerName = '',
    embedded = false,
}) {
    const [demoSex, setDemoSex] = useState('');
    const sex = sexProp ?? demoSex;
    const handleSexChange = onSexChange ?? setDemoSex;
    const options = optionsProp?.length
        ? optionsProp
        : [
              { value: 'male', label: 'Male' },
              { value: 'female', label: 'Female' },
          ];

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
            <form
                className="flex w-full flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();
                }}
            >
                <div className="w-full" role="group" aria-label="Gender options">
                    <div className="flex w-full flex-col gap-3">
                        {options.map((option) => {
                            const selected = sex === option.value;

                            return (
                                <GenderOptionButton
                                    key={option.value}
                                    label={option.label}
                                    selected={selected}
                                    disabled={processing}
                                    onSelect={() => handleSexChange(option.value)}
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

                {embedded ? null : (
                    <div className="flex w-full justify-center">
                        <Button
                            type="submit"
                            label={processing ? 'Saving…' : 'Continue'}
                            disabled={processing || !sex}
                            className="min-w-[200px] uppercase tracking-[0.08em]"
                        />
                    </div>
                )}
            </form>
        </OnboardingStepFrame>
    );
}

export default function Gender() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const options = onboarding.options ?? {};
    const { state, patch } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        sex: state.gender || profile.sex || '',
    });

    return (
        <OnboardingGenderInner
            sex={data.sex}
            options={options.sex?.length ? options.sex : undefined}
            errors={errors}
            processing={processing}
            onSexChange={(value) => {
                setData('sex', value);
                patch({ gender: value });
            }}
            onSubmit={() => {
                patch({ gender: data.sex });
                post(onboarding.urls?.gender ?? '/onboarding/gender');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'gender'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Gender.layout = customerOnboardingLayout;
