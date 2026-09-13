import Button from '../../Components/Atoms/Button/Button.jsx';
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
 * @param {boolean} [props.hasSubmittedPlan]
 * @param {import('react').ReactNode} [props.headerActions]
 * @param {() => void} [props.onViewSummary]
 * @param {() => void} [props.onEditPlan]
 * @param {() => void} [props.onStartPlan]
 * @param {() => void} [props.onActions]
 */
export function WelcomeBackInner({
    customerName = '',
    profile = null,
    hasSubmittedPlan = false,
    headerActions = null,
    onViewSummary,
    onEditPlan,
    onStartPlan,
    onActions,
}) {
    const dailyCaloriesLabel = dailyCaloriesDisplay(profile);

    return (
        <CustomerInertiaShell customerName={customerName} headerActions={headerActions}>
            <div className="rounded-[16px] border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="font-montserrat text-3xl font-semibold text-[#262A22]">
                    Welcome back{customerName ? `, ${customerName}` : ''}
                </h1>
                <p className="mt-3 text-sm text-[#555555]">
                    {hasSubmittedPlan
                        ? 'Your meal selections are saved. You can update them anytime before the kitchen starts production.'
                        : 'Your targets are set. Next, choose the meals you want for your week.'}
                </p>

                {profile ? (
                    <dl className="mt-8 grid gap-4 rounded-[12px] bg-[#F8F9F6] p-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-[#555555]">Daily calories</dt>
                            <dd className="mt-1 text-lg font-semibold text-[#262A22]">{dailyCaloriesLabel ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-[#555555]">Macro style</dt>
                            <dd className="mt-1 text-lg font-semibold capitalize text-[#262A22]">
                                {profile.macroSplitStyle?.replace('_', ' ')}
                            </dd>
                        </div>
                    </dl>
                ) : null}

                <div className="mt-8 rounded-[12px] border border-[#6E8C47]/20 bg-[#6E8C47]/5 p-6">
                    <h2 className="font-montserrat text-xl font-semibold text-[#262A22]">
                        {hasSubmittedPlan ? 'Update your meals' : 'Choose your meals'}
                    </h2>
                    <p className="mt-2 text-sm text-[#555555]">
                        Browse breakfasts, mains, salads, desserts, and optional soup — portioned to your{' '}
                        {dailyCaloriesLabel ?? 'plan'} kcal target.
                    </p>
                    <div className="mt-5 flex flex-col items-center gap-3">
                        <div className="flex flex-wrap justify-center gap-3">
                            {hasSubmittedPlan ? (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    label="View meal plan summary"
                                    onClick={onViewSummary}
                                    className="min-w-[200px]"
                                />
                            ) : null}
                            <Button
                                type="button"
                                variant="secondary"
                                label={hasSubmittedPlan ? 'Edit meal plan' : 'Start meal selection'}
                                onClick={hasSubmittedPlan ? onEditPlan : onStartPlan}
                                className="min-w-[200px]"
                            />
                        </div>
                        <Button type="button" label="Actions" onClick={onActions} className="min-w-[200px]" />
                    </div>
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.consultationUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.fulfillmentUrl]
 * @param {{ craftKey?: string; weekDuration?: number; submittedAt?: string } | null} [props.craftPlan]
 */
export default function Home({
    customerName,
    profile,
    consultationUrl = '/consultation/crafted-for-you',
    mealPlanSummaryUrl = '/app/meal-plan',
    fulfillmentUrl = '/checkout',
    craftPlan = null,
}) {
    return (
        <WelcomeBackInner
            customerName={customerName}
            profile={profile}
            hasSubmittedPlan={Boolean(craftPlan?.submittedAt)}
            headerActions={<CustomerAppHeaderActions />}
            onViewSummary={() => window.location.assign(mealPlanSummaryUrl)}
            onEditPlan={() => window.location.assign(consultationUrl)}
            onStartPlan={() => window.location.assign(consultationUrl)}
            onActions={() => window.location.assign(fulfillmentUrl)}
        />
    );
}

Home.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
