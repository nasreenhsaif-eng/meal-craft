import { Link, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import FulfillmentOptionCards from '../../Components/Molecules/Customer/FulfillmentOptionCards.jsx';
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
 * @param {'welcome' | 'receive'} [props.mode]
 * @param {string} [props.deliveryUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {() => void} [props.onEditMealsPlan]
 * @param {string} [props.planDateRangeLabel]
 * @param {boolean} [props.showPlanReady]
 */
export function WelcomeBackInner({
    customerName = '',
    profile = null,
    mode = 'welcome',
    deliveryUrl = '/checkout/delivery',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    onEditMealsPlan,
    planDateRangeLabel = '',
    showPlanReady = false,
}) {
    const isReceive = mode === 'receive';
    const dailyCaloriesLabel = dailyCaloriesDisplay(profile);
    const homeHref = String(homeUrl || '/app').split('?')[0] || '/app';

    return (
        <CustomerInertiaShell
            customerName={customerName}
            footerNav={
                isReceive ? null : (
                    <CustomerAppBottomNav
                        activeTab="home"
                        homeUrl={homeHref}
                        profileUrl={profileUrl}
                        shopUrl={shopUrl}
                        mealsUrl={mealPlanSummaryUrl}
                    />
                )
            }
        >
            <div
                className="w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8"
            >
                {isReceive ? (
                    <Link
                        href={homeHref}
                        className="mb-3 inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                    >
                        ← Back to home
                    </Link>
                ) : null}

                <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    {isReceive
                        ? 'How would you like to receive your meal plan?'
                        : `Welcome back${customerName ? `, ${customerName}` : ''}`}
                </h1>

                {isReceive ? (
                    <p className="mt-3 max-w-2xl font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                        Choose between fresh daily preparation delivered to your door or self-prepared recipes at
                        home.
                    </p>
                ) : (
                    <>
                        <p className="mt-3 max-w-2xl font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                            Looking forward to crafting your meals!
                        </p>
                        {showPlanReady ? (
                            <>
                                <p className="mt-2 max-w-2xl font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                                    Your meal plan is ready
                                    {planDateRangeLabel ? (
                                        <>
                                            {' '}
                                            for{' '}
                                            <span className="font-semibold text-[#262A22]">{planDateRangeLabel}</span>
                                        </>
                                    ) : (
                                        ' for next week'
                                    )}
                                    .
                                </p>
                                <div className="mt-6 flex w-full justify-center">
                                    <Button
                                        type="button"
                                        label="Choose your meals"
                                        variant="primary"
                                        onClick={onEditMealsPlan}
                                        className="min-w-[200px] uppercase tracking-[0.08em]"
                                    />
                                </div>
                                <p className="mt-3 max-w-2xl font-body text-[11px] leading-snug text-[#777777] sm:text-xs">
                                    Please submit your choices prior to end of day{' '}
                                    <span className="font-semibold uppercase tracking-wide text-[#555555]">Friday</span>,
                                    or our system will automatically choose the meals for your kind self!
                                </p>
                            </>
                        ) : null}
                    </>
                )}

                {!isReceive && profile ? (
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

                {isReceive ? <FulfillmentOptionCards deliveryUrl={deliveryUrl} className="mt-8" /> : null}
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.consultationUrl]
 * @param {string} [props.consultationEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.deliveryUrl]
 * @param {{ craftKey?: string; weekDuration?: number; submittedAt?: string } | null} [props.craftPlan]
 * @param {{ startsOn?: string; endsOn?: string; label?: string } | null} [props.planDateRange]
 * @param {boolean} [props.showPlanReady]
 */
export default function Home({
    customerName,
    profile,
    consultationUrl = '/consultation/crafted-for-you',
    consultationEditUrl = '/consultation/crafted-for-you/edit',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    deliveryUrl = '/checkout/delivery',
    craftPlan = null,
    planDateRange = null,
    showPlanReady = false,
}) {
    const page = usePage();
    const fromSummary = String(page.url ?? '').includes('from=summary');
    const mode = fromSummary ? 'receive' : 'welcome';
    const mealsPlanUrl =
        craftPlan != null && typeof consultationEditUrl === 'string' && consultationEditUrl.trim() !== ''
            ? consultationEditUrl
            : consultationUrl;

    return (
        <WelcomeBackInner
            customerName={customerName}
            profile={profile}
            mode={mode}
            deliveryUrl={deliveryUrl}
            homeUrl={homeUrl}
            profileUrl={profileUrl}
            shopUrl={shopUrl}
            mealPlanSummaryUrl={mealPlanSummaryUrl}
            onEditMealsPlan={() => {
                // Consultation is a standalone Blade/Vite page, not Inertia.
                window.location.assign(mealsPlanUrl);
            }}
            planDateRangeLabel={planDateRange?.label ?? ''}
            showPlanReady={showPlanReady}
        />
    );
}

Home.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
