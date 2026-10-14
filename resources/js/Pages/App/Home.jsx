import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
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

const GHOST_OUTLINE_CLASS =
    'w-full border border-[#5A6B44] bg-transparent text-[#5A6B44] hover:bg-[#5A6B44]/10';

/**
 * @param {{
 *   onEditProfile?: () => void;
 *   onEditMealsPlan?: () => void;
 *   onViewSummary?: () => void;
 * }} props
 */
function WelcomeBackGhostActions({ onEditProfile, onEditMealsPlan, onViewSummary }) {
    return (
        <div className="flex w-full min-w-0 flex-col gap-2.5" role="group" aria-label="Account options">
            <Button
                type="button"
                label="Edit your profile"
                variant="outline"
                size="sm"
                onClick={onEditProfile}
                className={GHOST_OUTLINE_CLASS}
            />
            <Button
                type="button"
                label="Edit your meals plan"
                variant="outline"
                size="sm"
                onClick={onEditMealsPlan}
                className={GHOST_OUTLINE_CLASS}
            />
            <Button
                type="button"
                label="View meal plan summary"
                variant="outline"
                size="sm"
                onClick={onViewSummary}
                className={GHOST_OUTLINE_CLASS}
            />
        </div>
    );
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
 * @param {import('react').ReactNode} [props.headerActions]
 * @param {() => void} [props.onEditProfile]
 * @param {() => void} [props.onEditMealsPlan]
 * @param {() => void} [props.onViewSummary]
 * @param {string} [props.planDateRangeLabel]
 * @param {boolean} [props.showPlanReady]
 */
export function WelcomeBackInner({
    customerName = '',
    profile = null,
    mode = 'welcome',
    deliveryUrl = '/checkout/delivery',
    headerActions = null,
    onEditProfile,
    onEditMealsPlan,
    onViewSummary,
    planDateRangeLabel = '',
    showPlanReady = false,
}) {
    const isReceive = mode === 'receive';
    const dailyCaloriesLabel = dailyCaloriesDisplay(profile);

    return (
        <CustomerInertiaShell customerName={customerName} headerActions={headerActions}>
            <div
                className={[
                    'w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8',
                    isReceive ? 'max-w-5xl' : '',
                ]
                    .join(' ')
                    .trim()}
            >
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
                                <div className="mt-6">
                                    <Button
                                        type="button"
                                        label="Choose your meals"
                                        variant="primary"
                                        onClick={onEditMealsPlan}
                                        className="w-full sm:w-auto"
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

                <div className="mt-8">
                    <WelcomeBackGhostActions
                        onEditProfile={onEditProfile}
                        onEditMealsPlan={onEditMealsPlan}
                        onViewSummary={onViewSummary}
                    />
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.consultationUrl]
 * @param {string} [props.consultationEditUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.profileEditUrl]
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
    mealPlanSummaryUrl = '/app/meal-plan',
    profileEditUrl = '/onboarding/gender',
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
            headerActions={<CustomerAppHeaderActions />}
            onEditProfile={() => router.visit(profileEditUrl)}
            onEditMealsPlan={() => {
                // Consultation is a standalone Blade/Vite page, not Inertia.
                window.location.assign(mealsPlanUrl);
            }}
            onViewSummary={() => router.visit(mealPlanSummaryUrl)}
            planDateRangeLabel={planDateRange?.label ?? ''}
            showPlanReady={showPlanReady}
        />
    );
}

Home.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
