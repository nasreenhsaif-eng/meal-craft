import OnboardingProvider from '../meal-craft/onboarding/OnboardingProvider.jsx';

/**
 * Shared Inertia layout for customer onboarding pages.
 * Use the same function reference on every step so the provider stays mounted between visits.
 *
 * @param {import('react').ReactElement} page
 */
export default function customerOnboardingLayout(page) {
    return <OnboardingProvider>{page}</OnboardingProvider>;
}
