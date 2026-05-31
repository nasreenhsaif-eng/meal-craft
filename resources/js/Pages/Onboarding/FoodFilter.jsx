import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import FoodFilterMultiSelect from '../../Components/MealSystem/FoodFilterMultiSelect.jsx';
import { FOOD_FILTER_OTHER_ID } from '../../Components/MealSystem/foodFilterOptions.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { OnboardingShell } from './Welcome.jsx';

/**
 * Food filter onboarding step (Storybook / Inertia).
 *
 * @param {{
 *   selectedFilters?: import('../../Components/MealSystem/foodFilterOptions.js').FoodFilterId[];
 *   otherText?: string;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   onSelectedFiltersChange?: (value: import('../../Components/MealSystem/foodFilterOptions.js').FoodFilterId[]) => void;
 *   onOtherTextChange?: (value: string) => void;
 *   onSubmit?: () => void;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 * }} props
 */
export function OnboardingFoodFilterInner({
    selectedFilters: selectedFiltersProp,
    otherText: otherTextProp,
    errors = {},
    processing = false,
    onSelectedFiltersChange,
    onOtherTextChange,
    onSubmit,
    steps = [],
    currentStep = 'food_filters',
    customerName = '',
}) {
    const [demoSelected, setDemoSelected] = useState([]);
    const [demoOtherText, setDemoOtherText] = useState('');
    const selectedFilters = selectedFiltersProp ?? demoSelected;
    const otherText = otherTextProp ?? demoOtherText;
    const handleSelectedChange = onSelectedFiltersChange ?? setDemoSelected;
    const handleOtherChange = onOtherTextChange ?? setDemoOtherText;

    return (
        <OnboardingShell
            title="Food filters"
            description="Select any ingredients or sensitivities we should avoid when planning your meals."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <form
                className="mx-auto flex w-full max-w-xl flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit?.();
                }}
            >
                <fieldset className="w-full min-w-0 border-0 p-0">
                    <legend className="sr-only">Food filters</legend>
                    <FoodFilterMultiSelect
                        value={selectedFilters}
                        onChange={handleSelectedChange}
                        otherText={otherText}
                        onOtherTextChange={handleOtherChange}
                    />
                    {errors.allergies ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.allergies}
                        </p>
                    ) : null}
                    {errors.allergy_other ? (
                        <p className="mt-3 text-center text-sm text-red-600" role="alert">
                            {errors.allergy_other}
                        </p>
                    ) : null}
                </fieldset>

                <div className="flex w-full justify-center">
                    <Button
                        type="submit"
                        label={processing ? 'Saving…' : 'Confirm'}
                        disabled={processing}
                        className="min-w-[200px] uppercase tracking-[0.08em]"
                    />
                </div>
            </form>
        </OnboardingShell>
    );
}

export default function FoodFilter() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};

    const { data, setData, post, processing, errors } = useForm({
        allergies: profile.allergies ?? [],
        allergy_other: profile.allergy_other ?? '',
    });

    const selectedFilters = Array.isArray(data.allergies) ? data.allergies : [];

    return (
        <OnboardingFoodFilterInner
            selectedFilters={selectedFilters}
            otherText={selectedFilters.includes(FOOD_FILTER_OTHER_ID) ? data.allergy_other : ''}
            errors={errors}
            processing={processing}
            onSelectedFiltersChange={(next) => {
                setData('allergies', next);
                if (!next.includes(FOOD_FILTER_OTHER_ID)) {
                    setData('allergy_other', '');
                }
            }}
            onOtherTextChange={(value) => setData('allergy_other', value)}
            onSubmit={() => post(onboarding.urls?.foodFilters ?? '/onboarding/food-filters')}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'food_filters'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

FoodFilter.layout = (page) => page;
