import { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import WheelDatePicker from '../../Components/Molecules/Onboarding/WheelDatePicker.jsx';
import {
    buildDayOptions,
    buildYearOptions,
    clampDay,
    defaultBirthdayValue,
    parseIsoDate,
    toIsoDate,
} from '../../Components/Molecules/Onboarding/wheelDateUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import { OnboardingShell } from './Welcome.jsx';

/**
 * Birthday wheel step markup (Storybook / Inertia).
 *
 * @param {{
 *   dateOfBirth?: string;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onDateChange?: (isoDate: string) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   minAge?: number;
 *   maxAge?: number;
 * }} props
 */
export function OnboardingBirthdayInner({
    dateOfBirth: dateOfBirthProp,
    errors = {},
    processing = false,
    onDateChange,
    onSubmit,
    steps = [],
    currentStep = 'birthday',
    customerName = '',
    minAge = 13,
    maxAge = 100,
}) {
    const parsedInitial = parseIsoDate(dateOfBirthProp) ?? defaultBirthdayValue();
    const [demoParts, setDemoParts] = useState(parsedInitial);

    const parts = onDateChange
        ? (parseIsoDate(dateOfBirthProp) ?? defaultBirthdayValue())
        : demoParts;
    const month = parts.month;
    const day = parts.day;
    const year = parts.year;

    const yearOptions = useMemo(() => buildYearOptions(minAge, maxAge), [minAge, maxAge]);
    const dayOptions = useMemo(() => buildDayOptions(month, year), [month, year]);

    const updateParts = (nextParts) => {
        const clamped = {
            ...nextParts,
            day: clampDay(nextParts.day, nextParts.month, nextParts.year),
        };

        if (onDateChange) {
            onDateChange(toIsoDate(clamped));
            return;
        }

        setDemoParts(clamped);
    };

    return (
        <OnboardingShell
            title="When is your birthday?"
            description="Your age helps us tailor recommendations to match your changing nutritional needs over time."
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
                <div className="w-full min-w-0">
                    <WheelDatePicker
                        className="w-full"
                        month={month}
                        day={day}
                        year={year}
                        dayOptions={dayOptions}
                        yearOptions={yearOptions}
                        onChange={updateParts}
                    />
                    {errors.date_of_birth ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.date_of_birth}
                        </p>
                    ) : null}
                </div>

                <div className="flex w-full justify-center">
                    <Button
                        type="submit"
                        label={processing ? 'Saving…' : 'Next'}
                        disabled={processing}
                        className="min-w-[200px] uppercase tracking-[0.08em]"
                    />
                </div>
            </form>
        </OnboardingShell>
    );
}

export default function Birthday() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch } = useOnboardingStore();
    const initialDate =
        state.birthdate ||
        profile.dateOfBirth ||
        profile.date_of_birth ||
        toIsoDate(defaultBirthdayValue());

    const { data, setData, post, processing, errors } = useForm({
        date_of_birth: initialDate,
    });

    return (
        <OnboardingBirthdayInner
            dateOfBirth={data.date_of_birth}
            errors={errors}
            processing={processing}
            onDateChange={(isoDate) => {
                setData('date_of_birth', isoDate);
                patch({ birthdate: isoDate });
            }}
            onSubmit={() => {
                patch({ birthdate: data.date_of_birth });
                post(onboarding.urls?.birthday ?? '/onboarding/birthday');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'birthday'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Birthday.layout = customerOnboardingLayout;
