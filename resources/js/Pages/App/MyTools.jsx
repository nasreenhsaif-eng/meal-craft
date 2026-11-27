import { useCallback, useEffect, useLayoutEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerDetailSection, {
    caloriesDisplay,
    displayValue,
    humanizeSlug,
    listDisplay,
} from '../../Components/Molecules/Customer/CustomerDetailSection.jsx';
import ActivePhaseCard from '../../Components/Molecules/Onboarding/ActivePhaseCard.jsx';
import { DailyTargetsSummaryInner } from '../Onboarding/DailyTargetsSummary.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    formatShoppingAmount,
    readCustomerShoppingList,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/** @typedef {'shopping' | 'targets' | 'body'} ToolsTabId */

const TOOLS_TABS = /** @type {const} */ ([
    { id: 'shopping', label: 'Shopping list' },
    { id: 'targets', label: 'Daily targets' },
    { id: 'body', label: 'Body & plan' },
]);

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {object} props.dailyTargets
 * @param {object | null} [props.bodyPlan]
 * @param {object | null} [props.activePhase]
 * @param {boolean} [props.showPeriodTracker]
 * @param {string | null} [props.periodTrackerUrl]
 * @param {string} [props.updateProtocolModeUrl]
 * @param {string} [props.craftedForYouUrl]
 * @param {string} [props.profileEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.toolsUrl]
 * @param {string} [props.favouritesUrl]
 * @param {string} [props.cartUrl]
 */
export function MyToolsInner({
    customerName = '',
    dailyTargets,
    bodyPlan = null,
    activePhase = null,
    showPeriodTracker = false,
    periodTrackerUrl = null,
    updateProtocolModeUrl = '/app/protocol-mode',
    craftedForYouUrl = '/consultation/crafted-for-you',
    profileEditUrl = '/onboarding/gender',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    toolsUrl = '/app/tools',
    favouritesUrl = '/app/favourites',
    cartUrl = '/app/cart',
}) {
    const page = usePage();
    const userId = page.props?.auth?.user?.id ?? null;
    const [shoppingList, setShoppingList] = useState(() => readCustomerShoppingList(userId));
    /** @type {[ToolsTabId, import('react').Dispatch<import('react').SetStateAction<ToolsTabId>>]} */
    const [activeTab, setActiveTab] = useState(/** @type {ToolsTabId} */ ('shopping'));

    useEffect(() => {
        setShoppingList(readCustomerShoppingList(userId));
    }, [userId]);

    const birthdayLabel =
        bodyPlan?.dateOfBirth != null && bodyPlan.dateOfBirth !== ''
            ? bodyPlan.age != null
                ? `${bodyPlan.dateOfBirth} (${bodyPlan.age})`
                : bodyPlan.dateOfBirth
            : bodyPlan?.age != null
              ? String(bodyPlan.age)
              : '—';

    const filterItems =
        Array.isArray(bodyPlan?.foodFilters) && bodyPlan.foodFilters.length > 0
            ? bodyPlan.foodFilters
            : bodyPlan?.allergies;

    /** @type {{ label: string; value: string; preserveCase?: boolean }[]} */
    const bodyPlanRows = [
        { label: 'Sex', value: displayValue(bodyPlan?.sexLabel) },
        { label: 'Diet protocol', value: displayValue(bodyPlan?.dietProtocolLabel) },
        ...(bodyPlan?.dietProtocol === 'cycle_sync'
            ? [
                  {
                      label: 'Protocol mode',
                      value: displayValue(bodyPlan?.protocolModeLabel),
                  },
              ]
            : []),
        { label: 'Birthday / age', value: birthdayLabel, preserveCase: true },
        { label: 'Height', value: bodyPlan?.heightCm != null ? `${bodyPlan.heightCm} cm` : '—' },
        { label: 'Weight', value: bodyPlan?.weightKg != null ? `${bodyPlan.weightKg} kg` : '—' },
        {
            label: 'Target weight',
            value: bodyPlan?.targetWeightKg != null ? `${bodyPlan.targetWeightKg} kg` : '—',
        },
        { label: 'Activity', value: displayValue(bodyPlan?.activityLevelLabel) },
        { label: 'Daily calorie range', value: caloriesDisplay(bodyPlan), preserveCase: true },
        { label: 'Macro style', value: humanizeSlug(bodyPlan?.macroSplitStyle) },
        { label: 'Food filters / allergies', value: listDisplay(filterItems) },
    ];

    useLayoutEffect(() => {
        window.scrollTo(0, 0);
    }, [activeTab]);

    const handleClear = () => {
        setShoppingList([]);
        writeCustomerShoppingList(userId, []);
    };

    const handleSelectTab = useCallback((tabId) => {
        setActiveTab(tabId);
    }, []);

    const shoppingPanel = (
        <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
            <h2 className="font-montserrat text-lg font-bold tracking-tight text-heading">Shopping list</h2>
            <p className="mt-1 font-body text-sm text-body">Ingredients you checked on your DIY meals.</p>

            {shoppingList.length === 0 ? (
                <p className="mt-4 font-body text-sm text-body">Your shopping list is empty.</p>
            ) : (
                <ul className="mt-4 space-y-1" role="list">
                    {shoppingList.map((row) => (
                        <li
                            key={row.key}
                            className="flex items-center justify-between gap-3 border-b border-gray-100 px-1 py-2.5 last:border-b-0"
                        >
                            <span className="min-w-0 flex-1 font-body text-sm text-heading">{row.name}</span>
                            {formatShoppingAmount(row.amount, row.unit) ? (
                                <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-body">
                                    {formatShoppingAmount(row.amount, row.unit)}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-4">
                <Button
                    type="button"
                    label="Clear shopping list"
                    variant="ghost"
                    size="sm"
                    className="w-full border border-[#C44F5D] text-[#C44F5D]"
                    disabled={shoppingList.length === 0}
                    onClick={handleClear}
                />
            </div>
        </section>
    );

    const targetsPanel = (
        <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
            <DailyTargetsSummaryInner targets={dailyTargets} embedded />
        </section>
    );

    const bodyPanel = (
        <div className="flex flex-col gap-4">
            {activePhase ? (
                <ActivePhaseCard
                    phaseId={activePhase.currentSyncPhase}
                    protocolMode={activePhase.protocolMode}
                    cycleDay={activePhase.cycleDay}
                    cycleLength={activePhase.cycleLength}
                    metadata={activePhase.currentPhaseMetadata}
                    mealPlanPayload={activePhase.mealPlanGenerationPayload}
                    updateProtocolModeUrl={updateProtocolModeUrl}
                    craftedForYouUrl={craftedForYouUrl}
                    showProtocolSelector
                />
            ) : null}
            <CustomerDetailSection
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
        </div>
    );

    const tabBody = (() => {
        switch (activeTab) {
            case 'targets':
                return targetsPanel;
            case 'body':
                return bodyPanel;
            case 'shopping':
            default:
                return shoppingPanel;
        }
    })();

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="tools"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    toolsUrl={toolsUrl}
                    favouritesUrl={favouritesUrl}
                />
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <div className="flex flex-col gap-2">
                    <Link
                        href={mealPlanSummaryUrl}
                        className="flex w-full items-center justify-between rounded-[12px] border border-brand-primary/30 bg-brand-primary/10 px-4 py-3 no-underline transition hover:border-brand-primary/50 hover:bg-brand-primary/20"
                    >
                        <span className="font-montserrat text-sm font-bold uppercase tracking-[0.08em] text-heading">
                            Meal plan
                        </span>
                        <span className="font-body text-xs text-body">Open your week</span>
                    </Link>
                    {showPeriodTracker && periodTrackerUrl ? (
                        <Link
                            href={periodTrackerUrl}
                            className="flex w-full items-center justify-between rounded-[12px] border border-brand-primary/30 bg-brand-primary/10 px-4 py-3 no-underline transition hover:border-brand-primary/50 hover:bg-brand-primary/20"
                        >
                            <span className="font-montserrat text-sm font-bold uppercase tracking-[0.08em] text-heading">
                                Period Tracker
                            </span>
                            <span className="font-body text-xs text-body">Analysis · Food · Exercise · Brain</span>
                        </Link>
                    ) : null}
                </div>

                <div
                    className="flex gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    role="tablist"
                    aria-label="My Tools"
                >
                    {TOOLS_TABS.map((tab) => {
                        const selected = activeTab === tab.id;

                        return (
                            <Button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={selected}
                                variant={selected ? 'primary' : 'tab'}
                                size="sm"
                                className="shrink-0"
                                onClick={() => handleSelectTab(/** @type {ToolsTabId} */ (tab.id))}
                            >
                                {tab.label}
                            </Button>
                        );
                    })}
                </div>

                <div className="pb-2">{tabBody}</div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function MyTools(props) {
    return <MyToolsInner {...props} />;
}

MyTools.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
