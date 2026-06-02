import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { GenderOptionCard, genderOptionIcon } from '../../Components/Molecules/Onboarding/GenderOptionCard.jsx';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import { OnboardingShell } from './Welcome.jsx';

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
}) {
    const [demoSex, setDemoSex] = useState('');
    const sex = sexProp ?? demoSex;
    const handleSexChange = onSexChange ?? setDemoSex;
    const options = optionsProp ?? [
        { value: 'male', label: 'Male' },
        { value: 'female', label: 'Female' },
    ];

    return (
        <OnboardingShell
            title="Create your profile"
            description="Select your gender so we can personalize calorie and macro calculations."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <form
                className="mx-auto flex w-full max-w-xl flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!sex) {
                        return;
                    }
                    onSubmit?.();
                }}
            >
                <fieldset className="w-full min-w-0 border-0 p-0">
                    <legend className="sr-only">Gender</legend>
                    <div
                        className="mx-auto grid w-full max-w-[280px] grid-cols-2 gap-3 sm:max-w-xs sm:gap-4"
                        role="group"
                        aria-label="Gender options"
                    >
                        {options.map((option) => (
                            <GenderOptionCard
                                key={option.value}
                                label={option.label}
                                selected={sex === option.value}
                                onSelect={() => handleSexChange(option.value)}
                                icon={genderOptionIcon(option.value)}
                                className="w-full"
                            />
                        ))}
                    </div>
                    {errors.sex ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.sex}
                        </p>
                    ) : null}
                </fieldset>

                <div className="flex w-full justify-center">
                    <Button
                        type="submit"
                        label={processing ? 'Saving…' : 'Continue'}
                        disabled={processing || !sex}
                        className="min-w-[160px]"
                    />
                </div>
            </form>
        </OnboardingShell>
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
            options={options.sex ?? []}
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
