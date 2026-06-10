import { Link, router, usePage } from '@inertiajs/react';
import { ONBOARDING_STORAGE_KEY } from '../../../meal-craft/onboarding/onboardingConstants.js';
import { onboardingUrls } from '../../../meal-craft/mealCraftPageProps.js';

/**
 * Temporary customer app header utilities for QA (reset onboarding + sign out).
 *
 * @param {{ resetUrl?: string }} props
 */
export default function CustomerAppHeaderActions({ resetUrl: resetUrlProp }) {
    const urls = onboardingUrls(usePage().props);
    const resetUrl = resetUrlProp ?? urls.reset ?? '/onboarding/reset';

    const handleResetOnboarding = () => {
        if (typeof window !== 'undefined') {
            try {
                sessionStorage.removeItem(ONBOARDING_STORAGE_KEY);
            } catch {
                // ignore quota errors
            }
        }

        router.post(resetUrl, {}, { preserveScroll: false });
    };

    return (
        <div className="flex flex-wrap items-center justify-end gap-4 sm:gap-5">
            <button
                type="button"
                onClick={handleResetOnboarding}
                className="font-montserrat text-sm font-semibold text-[#2F4C9B] transition-colors hover:text-[#1e3468] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2F4C9B]/30 focus-visible:ring-offset-2"
            >
                🔧 Edit Profile / Reset
            </button>
            <Link
                href="/logout"
                method="post"
                as="button"
                className="font-montserrat text-sm font-medium text-[#6B7280] transition-colors hover:text-[#B42318] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#B42318]/25 focus-visible:ring-offset-2"
            >
                Sign Out
            </Link>
        </div>
    );
}
