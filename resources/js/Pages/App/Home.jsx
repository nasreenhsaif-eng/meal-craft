import { router } from '@inertiajs/react';
import OnboardingOptionButton from '../../Components/Atoms/Button/OnboardingOptionButton.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { formatCalorieRange } from '../../meal-craft/dailyTargetsCalculator.js';

/**
 * @param {{
 *   dailyCalorieTarget?: number | null;
 *   dailyCaloriesMin?: number | null;
 *   dailyCaloriesMax?: number | null;
 * } | null | undefined} profile
 */
function dailyCaloriesDisplay(profile) {
    const min = Number(profile?.dailyCaloriesMin);
    const max = Number(profile?.dailyCaloriesMax);

    if (Number.isFinite(min) && Number.isFinite(max) && min > 0 && max > 0) {
        return formatCalorieRange(min, max);
    }

    const snapped = Number(profile?.dailyCalorieTarget);

    return Number.isFinite(snapped) && snapped > 0 ? snapped : null;
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {{
 *   dailyCalorieTarget?: number | null;
 *   dailyCaloriesMin?: number | null;
 *   dailyCaloriesMax?: number | null;
 *   macroSplitStyle?: string | null;
 * } | null} [props.profile]
 * @param {import('react').ReactNode} [props.headerActions]
 * @param {() => void} [props.onEditProfile]
 * @param {() => void} [props.onEditMealsPlan]
 * @param {() => void} [props.onViewSummary]
 */
export function WelcomeBackInner({
    customerName = '',
    profile = null,
    headerActions = null,
    onEditProfile,
    onEditMealsPlan,
    onViewSummary,
}) {
    const dailyCaloriesLabel = dailyCaloriesDisplay(profile);

    return (
        <CustomerInertiaShell customerName={customerName} headerActions={headerActions}>
            <div className="w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8">
                <h1 className="font-montserrat text-3xl font-semibold text-[#262A22]">
                    Welcome back{customerName ? `, ${customerName}` : ''}
                </h1>
                <p className="mt-3 text-sm text-[#555555]">
                    Pick up where you left off — edit your profile, update your meals, or review your plan.
                </p>

                {profile ? (
                    <dl className="mt-8 grid w-full gap-4 rounded-[12px] bg-[#F8F9F6] p-4 text-sm sm:grid-cols-2 sm:p-5">
                        <div className="min-w-0">
                            <dt className="text-[#555555]">Daily calories</dt>
                            <dd className="mt-1 text-lg font-semibold text-[#262A22]">{dailyCaloriesLabel ?? '—'}</dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-[#555555]">Macro style</dt>
                            <dd className="mt-1 text-lg font-semibold capitalize text-[#262A22]">
                                {profile.macroSplitStyle?.replace('_', ' ')}
                            </dd>
                        </div>
                    </dl>
                ) : null}

                <div
                    className="mt-8 flex w-full min-w-0 flex-col gap-2.5"
                    role="group"
                    aria-label="Welcome back options"
                >
                    <OnboardingOptionButton label="Edit your profile" size="sm" onSelect={onEditProfile} />
                    <OnboardingOptionButton label="Edit your meals plan" size="sm" onSelect={onEditMealsPlan} />
                    <OnboardingOptionButton label="View meal plan summary" size="sm" onSelect={onViewSummary} />
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.consultationUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.profileEditUrl]
 * @param {{ craftKey?: string; weekDuration?: number; submittedAt?: string } | null} [props.craftPlan]
 */
export default function Home({
    customerName,
    profile,
    consultationUrl = '/consultation/crafted-for-you',
    mealPlanSummaryUrl = '/app/meal-plan',
    profileEditUrl = '/onboarding/gender',
}) {
    return (
        <WelcomeBackInner
            customerName={customerName}
            profile={profile}
            headerActions={<CustomerAppHeaderActions />}
            onEditProfile={() => router.visit(profileEditUrl)}
            onEditMealsPlan={() => {
                // Consultation is a standalone Blade/Vite page, not Inertia.
                window.location.assign(consultationUrl);
            }}
            onViewSummary={() => router.visit(mealPlanSummaryUrl)}
        />
    );
}

Home.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
