import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import { OnboardingShell } from './Welcome.jsx';

export default function Meals() {
    const onboarding = onboardingFromPage(usePage().props);

    return (
        <OnboardingShell
            title="Choose your meals"
            description="Explore the consultation experience to pick meals that fit your craft. You can return here when you are ready to continue."
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'meals'}
            customerName={onboarding.customerName ?? ''}
        >
            <div className="flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    label="Open meal consultation"
                    onClick={() => window.location.assign(onboarding.urls?.consultation ?? '/consultation/crafted-for-you')}
                />
                <Button
                    type="button"
                    label="Continue"
                    onClick={() => router.post(onboarding.urls?.meals ?? '/onboarding/meals')}
                    className="min-w-[160px]"
                />
            </div>
        </OnboardingShell>
    );
}

Meals.layout = (page) => page;
