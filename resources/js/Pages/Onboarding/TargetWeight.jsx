import { useForm, usePage } from '@inertiajs/react';
import { OnboardingWeightInner } from './Weight.jsx';
import { defaultWeightKg, resolveTargetWeightKg, resolveWeightKg } from '../../Components/Molecules/Onboarding/weightUtils.js';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';

export function OnboardingTargetWeightInner({ currentStep = 'target_weight', ...props }) {
    return (
        <OnboardingWeightInner
            {...props}
            title="What is your target weight?"
            description="Your target weight helps us personalize calorie and macro recommendations for your goal."
            currentStep={currentStep}
            errorField="target_weight_kg"
        />
    );
}

export default function TargetWeight() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const currentWeightKg = resolveWeightKg(profile.weightKg ?? profile.weight_kg ?? defaultWeightKg());

    const { data, setData, post, processing, errors } = useForm({
        target_weight_kg: resolveTargetWeightKg(
            profile.targetWeightKg ?? profile.target_weight_kg,
            currentWeightKg,
        ),
    });

    return (
        <OnboardingTargetWeightInner
            weightKg={Number(data.target_weight_kg) || currentWeightKg}
            errors={errors}
            processing={processing}
            onWeightKgChange={(value) => setData('target_weight_kg', value)}
            onSubmit={() => post(onboarding.urls?.targetWeight ?? '/onboarding/target-weight')}
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'target_weight'}
            customerName={onboarding.customerName ?? ''}
        />
    );
}

TargetWeight.layout = (page) => page;
