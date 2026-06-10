import OnboardingProvider from '../meal-craft/onboarding/OnboardingProvider.jsx';
import { resolveInertiaLayoutChild } from '../lib/resolveInertiaLayoutChild.js';

/**
 * Shared Inertia layout for customer onboarding pages.
 *
 * @param {import('react').ReactElement | { children?: import('react').ReactNode }} pageOrProps
 */
export default function customerOnboardingLayout(pageOrProps) {
    return <OnboardingProvider>{resolveInertiaLayoutChild(pageOrProps)}</OnboardingProvider>;
}
