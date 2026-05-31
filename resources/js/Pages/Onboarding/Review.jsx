import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { OnboardingShell } from './Welcome.jsx';

export default function Review({ reviewPlan = null }) {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile;

    return (
        <OnboardingShell
            title="Review your plan"
            description="Confirm your details before we unlock your customer home."
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'review'}
            customerName={onboarding.customerName ?? ''}
        >
            {profile ? (
                <dl className="grid gap-4 rounded-[12px] bg-[#F8F9F6] p-5 text-sm">
                    <div className="flex justify-between gap-4">
                        <dt className="text-[#555555]">Daily calories</dt>
                        <dd className="font-semibold">{profile.dailyCalorieTarget}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-[#555555]">Macro style</dt>
                        <dd className="font-semibold">{profile.macroSplitStyle}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-[#555555]">Weight</dt>
                        <dd className="font-semibold">{profile.weightKg} kg</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-[#555555]">Height</dt>
                        <dd className="font-semibold">{profile.heightCm} cm</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-[#555555]">Age</dt>
                        <dd className="font-semibold">{profile.age}</dd>
                    </div>
                    {profile.goal ? (
                        <div className="flex justify-between gap-4">
                            <dt className="text-[#555555]">Goal</dt>
                            <dd className="font-semibold capitalize">{String(profile.goal).replace('_', ' ')}</dd>
                        </div>
                    ) : null}
                    {reviewPlan ? (
                        <div className="flex justify-between gap-4">
                            <dt className="text-[#555555]">Plan calories</dt>
                            <dd className="font-semibold">{reviewPlan.fixed?.calories ?? '—'}</dd>
                        </div>
                    ) : null}
                </dl>
            ) : null}

            <div className="mt-8">
                <Button
                    type="button"
                    label="Finish onboarding"
                    onClick={() => router.post(onboarding.urls?.review ?? '/onboarding/review')}
                    className="min-w-[160px]"
                />
            </div>
        </OnboardingShell>
    );
}

Review.layout = (page) => page;
