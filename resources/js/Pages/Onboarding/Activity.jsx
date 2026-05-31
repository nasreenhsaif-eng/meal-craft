import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import HeightSnapColumn from '../../Components/Molecules/Onboarding/HeightSnapColumn.jsx';
import {
    ACTIVITY_LEVEL_VALUES,
    activityDescription,
    activityLabel,
    defaultActivityLevel,
    resolveActivityLevel,
} from '../../Components/Molecules/Onboarding/activityUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
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
                className="flex w-full flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();
                }}
            >
                <div className="mx-auto w-full max-w-[300px]">
                    <HeightSnapColumn
                        ariaLabel="Daily activity level"
                        items={ACTIVITY_LEVEL_VALUES}
                        value={activityLevel}
                        onChange={setActivityLevel}
                        formatItem={(item) => activityLabel(String(item))}
                    />
                </div>

                <p className="block w-full shrink-0 text-center font-montserrat text-sm leading-normal text-[#555555]">
                    {activityDescription(activityLevel)}
                </p>

                {errors.activity_level ? (
                    <p className="text-center text-sm text-red-600" role="alert">
                        {errors.activity_level}
                    </p>
                ) : null}

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

export default function Activity() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};

    const { data, setData, post, processing, errors } = useForm({
        activity_level: resolveActivityLevel(profile.activityLevel ?? profile.activity_level ?? defaultActivityLevel()),
    });

    return (
        <OnboardingActivityInner
            activityLevel={data.activity_level}
            errors={errors}
            processing={processing}
            onActivityLevelChange={(value) => setData('activity_level', value)}
            onSubmit={() => post(onboarding.urls?.activity ?? '/onboarding/activity')}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'activity'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Activity.layout = (page) => page;
