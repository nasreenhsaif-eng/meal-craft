import { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import HeightSnapColumn from '../../Components/Molecules/Onboarding/HeightSnapColumn.jsx';
import WeightUnitToggle from '../../Components/Molecules/Onboarding/WeightUnitToggle.jsx';
import {
    WEIGHT_KG_OPTIONS,
    WEIGHT_LB_OPTIONS,
    clampWeightKg,
    defaultWeightKg,
    kgToLb,
    lbToKg,
    resolveWeightKg,
} from '../../Components/Molecules/Onboarding/weightUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.js';
import { OnboardingShell } from './Welcome.jsx';

/**
 * @param {{
 *   weightKg?: number;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onWeightKgChange?: (value: number) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   defaultUnit?: 'kg' | 'lb';
 *   title?: string;
 *   description?: string;
 *   errorField?: string;
 * }} props
 */
export function OnboardingWeightInner({
    weightKg: weightKgProp,
    errors = {},
    processing = false,
    onWeightKgChange,
    onSubmit,
    steps = [],
    currentStep = 'weight',
    customerName = '',
    defaultUnit = 'kg',
    title = 'How much do you weigh?',
    description = 'Weight is used alongside height to accurately calculate your Total Daily Energy Expenditure (TDEE) and target calories.',
    errorField = 'weight_kg',
}) {
    const isControlled = onWeightKgChange !== undefined;
    const initialKg = resolveWeightKg(weightKgProp);

    const [unit, setUnit] = useState(defaultUnit);
    const [demoWeightKg, setDemoWeightKg] = useState(initialKg);

    const weightKg = isControlled ? resolveWeightKg(weightKgProp) : demoWeightKg;

    const wheelOptions = unit === 'kg' ? WEIGHT_KG_OPTIONS : WEIGHT_LB_OPTIONS;
    const wheelValue = useMemo(() => {
        if (unit === 'kg') {
            return clampWeightKg(weightKg);
        }

        return kgToLb(weightKg);
    }, [unit, weightKg]);

    const setWeightKg = (nextKg) => {
        const clamped = clampWeightKg(nextKg);

        if (isControlled) {
            onWeightKgChange?.(clamped);
            return;
        }

        setDemoWeightKg(clamped);
    };

    const handleWheelChange = (nextValue) => {
        if (unit === 'kg') {
            setWeightKg(Number(nextValue));
            return;
        }

        setWeightKg(lbToKg(Number(nextValue)));
    };

    return (
        <OnboardingShell
            title={title}
            description={description}
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
                <div className="flex w-full items-center justify-center gap-3 sm:gap-4">
                    <div className="w-[180px] min-w-0 shrink-0">
                        <HeightSnapColumn
                            ariaLabel={unit === 'kg' ? 'Weight in kilograms' : 'Weight in pounds'}
                            items={wheelOptions}
                            value={wheelValue}
                            onChange={handleWheelChange}
                            unitLabel={unit}
                        />
                    </div>

                    <WeightUnitToggle className="shrink-0" value={unit} onChange={setUnit} />
                </div>

                {errors[errorField] ? (
                    <p className="text-center text-sm text-red-600" role="alert">
                        {errors[errorField]}
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

export default function Weight() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        weight_kg: resolveWeightKg(state.weight ?? profile.weightKg ?? profile.weight_kg ?? defaultWeightKg()),
    });

    return (
        <OnboardingWeightInner
            weightKg={Number(data.weight_kg) || defaultWeightKg()}
            errors={errors}
            processing={processing}
            onWeightKgChange={(value) => {
                setData('weight_kg', value);
                patch({ weight: Number(value) });
            }}
            onSubmit={() => {
                patch({ weight: Number(data.weight_kg) });
                post(onboarding.urls?.weight ?? '/onboarding/weight');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'weight'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Weight.layout = customerOnboardingLayout;
