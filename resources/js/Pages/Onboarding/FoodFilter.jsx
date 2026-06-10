import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import FoodFilterMultiSelect from '../../Components/MealSystem/FoodFilterMultiSelect.jsx';
import { FOOD_FILTER_OTHER_ID } from '../../Components/MealSystem/foodFilterOptions.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { useOnboardingStore } from '../../meal-craft/onboarding/OnboardingProvider.jsx';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import OnboardingStepFrame from '../../Components/Molecules/Onboarding/OnboardingStepFrame.jsx';

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
 *   embedded?: boolean;
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
    embedded = false,
}) {
    const [demoSelected, setDemoSelected] = useState([]);
    const [demoOtherText, setDemoOtherText] = useState('');
    const selectedFilters = selectedFiltersProp ?? demoSelected;
    const otherText = otherTextProp ?? demoOtherText;
    const handleSelectedChange = onSelectedFiltersChange ?? setDemoSelected;
    const handleOtherChange = onOtherTextChange ?? setDemoOtherText;

    return (
        <OnboardingStepFrame
            embedded={embedded}
            title="Food filters"
            description="Select any ingredients or sensitivities we should avoid when planning your meals."
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader
        >
            <form
                className="flex w-full flex-col gap-6 md:mx-auto md:max-w-xl"
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

                {embedded ? null : (
                    <div className="flex w-full justify-center">
                        <Button
                            type="submit"
                            label={processing ? 'Saving…' : 'Confirm'}
                            disabled={processing}
                            className="min-w-[200px] uppercase tracking-[0.08em]"
                        />
                    </div>
                )}
            </form>
        </OnboardingStepFrame>
    );
}

export default function FoodFilter() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const { state, patch } = useOnboardingStore();

    const { data, setData, post, processing, errors } = useForm({
        allergies: state.foodFilters.length ? state.foodFilters : (profile.allergies ?? []),
        allergy_other: state.allergyOther || profile.allergy_other || '',
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
                patch({ foodFilters: next });
                if (!next.includes(FOOD_FILTER_OTHER_ID)) {
                    setData('allergy_other', '');
                    patch({ allergyOther: '' });
                }
            }}
            onOtherTextChange={(value) => {
                setData('allergy_other', value);
                patch({ allergyOther: value });
            }}
            onSubmit={() => {
                patch({ foodFilters: data.allergies, allergyOther: data.allergy_other });
                post(onboarding.urls?.foodFilters ?? '/onboarding/food-filters');
            }}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'food_filters'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

FoodFilter.layout = customerOnboardingLayout;
