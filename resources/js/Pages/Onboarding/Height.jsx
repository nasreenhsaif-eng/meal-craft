import { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import HeightFeetInchesPicker from '../../Components/Molecules/Onboarding/HeightFeetInchesPicker.jsx';
import HeightSnapColumn from '../../Components/Molecules/Onboarding/HeightSnapColumn.jsx';
import MeasurementUnitToggle from '../../Components/Molecules/Onboarding/MeasurementUnitToggle.jsx';
import {
    HEIGHT_CM_OPTIONS,
    clampFeetInches,
    clampHeightCm,
    cmToFeetInches,
    defaultHeightCm,
    feetInchesToCm,
} from '../../Components/Molecules/Onboarding/heightUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { OnboardingShell } from './Welcome.jsx';

/**
 * @param {{
 *   heightCm?: number;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onHeightCmChange?: (value: number) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   defaultUnit?: 'cm' | 'ft_in';
 * }} props
 */
export function OnboardingHeightInner({
    heightCm: heightCmProp,
    errors = {},
    processing = false,
    onHeightCmChange,
    onSubmit,
    steps = [],
    currentStep = 'height',
    customerName = '',
    defaultUnit = 'cm',
}) {
    const isControlled = onHeightCmChange !== undefined;
    const initialCm = clampHeightCm(Number(heightCmProp) || defaultHeightCm());
    const initialConverted = cmToFeetInches(initialCm);
    const initialParts = clampFeetInches(initialConverted.feet, initialConverted.inches);

    const [unit, setUnit] = useState(defaultUnit);
    const [demoHeightCm, setDemoHeightCm] = useState(initialCm);
    const [demoFeet, setDemoFeet] = useState(initialParts.feet);
    const [demoInches, setDemoInches] = useState(initialParts.inches);

    const heightCm = isControlled ? clampHeightCm(Number(heightCmProp) || defaultHeightCm()) : demoHeightCm;
    const feetInches = useMemo(() => {
        const converted = cmToFeetInches(heightCm);

        return clampFeetInches(converted.feet, converted.inches);
    }, [heightCm]);
    const feet = isControlled ? feetInches.feet : demoFeet;
    const inches = isControlled ? feetInches.inches : demoInches;

    const setHeightCm = (nextCm) => {
        const clamped = clampHeightCm(nextCm);

        if (isControlled) {
            onHeightCmChange?.(clamped);
            return;
        }

        setDemoHeightCm(clamped);
        const converted = cmToFeetInches(clamped);
        const parts = clampFeetInches(converted.feet, converted.inches);
        setDemoFeet(parts.feet);
        setDemoInches(parts.inches);
    };

    const handleFeetChange = (nextFeet) => {
        const clamped = clampFeetInches(nextFeet, inches);

        if (!isControlled) {
            setDemoFeet(clamped.feet);
            setDemoInches(clamped.inches);
        }

        setHeightCm(feetInchesToCm(clamped.feet, clamped.inches));
    };

    const handleInchesChange = (nextInches) => {
        const clamped = clampFeetInches(feet, nextInches);

        if (!isControlled) {
            setDemoFeet(clamped.feet);
            setDemoInches(clamped.inches);
        }

        setHeightCm(feetInchesToCm(clamped.feet, clamped.inches));
    };

    return (
        <OnboardingShell
            title="How tall are you?"
            description="Height is used to calculate your body mass index (BMI) and create personalized health goals."
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
                    <div className={`min-w-0 shrink-0 ${unit === 'cm' ? 'w-[168px]' : 'w-[248px]'}`}>
                        {unit === 'cm' ? (
                            <HeightSnapColumn
                                ariaLabel="Height in centimeters"
                                items={HEIGHT_CM_OPTIONS}
                                value={heightCm}
                                onChange={(next) => setHeightCm(Number(next))}
                                unitLabel="cm"
                            />
                        ) : (
                            <HeightFeetInchesPicker
                                feet={feet}
                                inches={inches}
                                onFeetChange={handleFeetChange}
                                onInchesChange={handleInchesChange}
                            />
                        )}
                    </div>

                    <MeasurementUnitToggle className="shrink-0" value={unit} onChange={setUnit} />
                </div>

                {errors.height_cm ? (
                    <p className="text-center text-sm text-red-600" role="alert">
                        {errors.height_cm}
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

export default function Height() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};

    const { data, setData, post, processing, errors } = useForm({
        height_cm: profile.heightCm ?? profile.height_cm ?? defaultHeightCm(),
    });

    return (
        <OnboardingHeightInner
            heightCm={Number(data.height_cm) || defaultHeightCm()}
            errors={errors}
            processing={processing}
            onHeightCmChange={(value) => setData('height_cm', value)}
            onSubmit={() => post(onboarding.urls?.height ?? '/onboarding/height')}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'height'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

Height.layout = (page) => page;
