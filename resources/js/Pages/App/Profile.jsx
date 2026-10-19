import { Link, router } from '@inertiajs/react';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { formatCalorieRange } from '../../meal-craft/dailyTargetsCalculator.js';

/**
 * @param {unknown} value
 * @returns {string}
 */
function displayValue(value) {
    if (value == null || value === '') {
        return '—';
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return String(value);
}

/**
 * @param {string | null | undefined} slug
 * @returns {string}
 */
function humanizeSlug(slug) {
    if (slug == null || slug === '') {
        return '—';
    }

    return slug.replaceAll('_', ' ');
}

/**
 * @param {string[] | null | undefined} items
 * @returns {string}
 */
function listDisplay(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return '—';
    }

    return items.map((item) => humanizeSlug(item)).join(', ');
}

/**
 * @param {{
 *   dailyCaloriesMin?: number | null;
 *   dailyCaloriesMax?: number | null;
 *   dailyCalorieTarget?: number | null;
 * } | null | undefined} details
 */
function caloriesDisplay(details) {
    const min = Number(details?.dailyCaloriesMin);
    const max = Number(details?.dailyCaloriesMax);

    if (Number.isFinite(min) && Number.isFinite(max) && min > 0 && max > 0) {
        return formatCalorieRange(min, max);
    }

    const snapped = Number(details?.dailyCalorieTarget);

    return Number.isFinite(snapped) && snapped > 0 ? String(snapped) : '—';
}

/**
 * @param {{
 *   title: string;
 *   rows: { label: string; value: string; preserveCase?: boolean }[];
 *   action?: import('react').ReactNode;
 *   className?: string;
 * }} props
 */
function DetailSection({ title, rows, action = null, className = '' }) {
    return (
        <section
            className={[
                'w-full min-w-0 rounded-[16px] border border-gray-200 bg-white px-4 py-6 shadow-sm sm:p-8',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div className="flex items-start justify-between gap-4">
                <h2 className="font-montserrat text-lg font-semibold tracking-tight text-[#262A22]">
                    {title}
                </h2>
                {action}
            </div>
            <dl className="mt-3 divide-y divide-gray-100">
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className="grid gap-1 py-3 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4"
                    >
                        <dt className="font-montserrat text-xs font-bold uppercase tracking-[0.08em] text-[#6B7280]">
                            {row.label}
                        </dt>
                        <dd
                            className={[
                                'min-w-0 font-body text-base text-[#262A22]',
                                row.preserveCase ? '' : 'capitalize',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        >
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.profileEditUrl]
 * @param {string} [props.logoutUrl]
 * @param {{
 *   name?: string;
 *   email?: string | null;
 *   phone?: string | null;
 *   address?: string | null;
 *   deliveryAddress?: string | null;
 *   cards?: string[] | null;
 *   sexLabel?: string | null;
 *   dietProtocolLabel?: string | null;
 *   dateOfBirth?: string | null;
 *   age?: number | null;
 *   heightCm?: number | null;
 *   weightKg?: number | null;
 *   targetWeightKg?: number | null;
 *   activityLevelLabel?: string | null;
 *   dailyCalorieTarget?: number | null;
 *   dailyCaloriesMin?: number | null;
 *   dailyCaloriesMax?: number | null;
 *   macroSplitStyle?: string | null;
 *   foodFilters?: string[];
 *   allergies?: string[];
 * } | null} [props.details]
 */
export function ProfileInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    profileEditUrl = '/onboarding/gender',
    logoutUrl = '/logout',
    details = null,
}) {
    const birthdayLabel =
        details?.dateOfBirth != null && details.dateOfBirth !== ''
            ? details.age != null
                ? `${details.dateOfBirth} (${details.age})`
                : details.dateOfBirth
            : details?.age != null
              ? String(details.age)
              : '—';

    const filterItems =
        Array.isArray(details?.foodFilters) && details.foodFilters.length > 0
            ? details.foodFilters
            : details?.allergies;

    const cardsDisplay =
        Array.isArray(details?.cards) && details.cards.length > 0
            ? details.cards.join(', ')
            : 'None saved';

    /** @type {{ label: string; value: string; preserveCase?: boolean }[]} */
    const contactRows = [
        { label: 'Name', value: displayValue(details?.name || customerName) },
        { label: 'Email', value: displayValue(details?.email), preserveCase: true },
        { label: 'Phone', value: displayValue(details?.phone), preserveCase: true },
        { label: 'Address', value: displayValue(details?.address) },
        {
            label: 'Delivery address',
            value: displayValue(details?.deliveryAddress),
            preserveCase: true,
        },
        { label: 'Cards', value: cardsDisplay, preserveCase: true },
    ];

    /** @type {{ label: string; value: string; preserveCase?: boolean }[]} */
    const bodyPlanRows = [
        { label: 'Sex', value: displayValue(details?.sexLabel) },
        { label: 'Diet protocol', value: displayValue(details?.dietProtocolLabel) },
        { label: 'Birthday / age', value: birthdayLabel, preserveCase: true },
        { label: 'Height', value: details?.heightCm != null ? `${details.heightCm} cm` : '—' },
        { label: 'Weight', value: details?.weightKg != null ? `${details.weightKg} kg` : '—' },
        {
            label: 'Target weight',
            value: details?.targetWeightKg != null ? `${details.targetWeightKg} kg` : '—',
        },
        { label: 'Activity', value: displayValue(details?.activityLevelLabel) },
        { label: 'Daily calorie range', value: caloriesDisplay(details), preserveCase: true },
        { label: 'Macro style', value: humanizeSlug(details?.macroSplitStyle) },
        { label: 'Food filters / allergies', value: listDisplay(filterItems) },
    ];

    return (
        <CustomerInertiaShell
            customerName={customerName}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="profile"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                />
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    Your details
                </h1>

                <DetailSection
                    title="Body & plan"
                    rows={bodyPlanRows}
                    action={
                        <Link
                            href={profileEditUrl}
                            className="shrink-0 pt-0.5 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                        >
                            Edit
                        </Link>
                    }
                />
                <DetailSection title="Contact" rows={contactRows} />

                <div className="mt-2 flex justify-center pb-2">
                    <button
                        type="button"
                        onClick={() => router.post(logoutUrl)}
                        className="font-montserrat text-sm font-semibold text-[#6B7280] underline decoration-[#6B7280]/40 underline-offset-2 hover:text-[#374151] hover:decoration-[#374151]"
                    >
                        Sign out
                    </button>
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.profileEditUrl]
 * @param {string} [props.logoutUrl]
 * @param {object | null} [props.details]
 */
export default function Profile({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    profileEditUrl = '/onboarding/gender',
    logoutUrl = '/logout',
    details = null,
}) {
    return (
        <ProfileInner
            customerName={customerName}
            homeUrl={homeUrl}
            profileUrl={profileUrl}
            shopUrl={shopUrl}
            mealPlanSummaryUrl={mealPlanSummaryUrl}
            profileEditUrl={profileEditUrl}
            logoutUrl={logoutUrl}
            details={details}
        />
    );
}

Profile.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
