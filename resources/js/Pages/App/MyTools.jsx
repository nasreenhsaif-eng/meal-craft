import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    formatShoppingAmount,
    readCustomerShoppingList,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.favouritesUrl]
 * @param {string} [props.toolsUrl]
 */
export function MyToolsInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    favouritesUrl = '/app/favourites',
    toolsUrl = '/app/tools',
}) {
    const page = usePage();
    const userId = page.props?.auth?.user?.id ?? null;
    const [shoppingList, setShoppingList] = useState(() => readCustomerShoppingList(userId));

    useEffect(() => {
        setShoppingList(readCustomerShoppingList(userId));
    }, [userId]);

    const handleClear = () => {
        setShoppingList([]);
        writeCustomerShoppingList(userId, []);
    };

    return (
        <CustomerInertiaShell
            customerName={customerName}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="tools"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    favouritesUrl={favouritesUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-6">
                <div>
                    <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                        My Tools
                    </h1>
                    <p className="mt-2 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                        Shopping list from your recipes, plus kitchen helpers.
                    </p>
                </div>

                <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
                    <h2 className="font-montserrat text-lg font-bold tracking-tight text-[#262A22]">
                        Shopping list
                    </h2>
                    <p className="mt-1 font-body text-sm text-[#555555]">
                        Ingredients you checked on your DIY meals.
                    </p>

                    {shoppingList.length === 0 ? (
                        <p className="mt-4 font-body text-sm text-[#555555]">Your shopping list is empty.</p>
                    ) : (
                        <ul className="mt-4 space-y-1" role="list">
                            {shoppingList.map((row) => (
                                <li
                                    key={row.key}
                                    className="flex items-center justify-between gap-3 border-b border-gray-100 px-1 py-2.5 last:border-b-0"
                                >
                                    <span className="min-w-0 flex-1 font-body text-sm text-[#262A22]">
                                        {row.name}
                                    </span>
                                    {formatShoppingAmount(row.amount, row.unit) ? (
                                        <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-[#555555]">
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

                <section className="rounded-[16px] border border-gray-200 bg-[#F8F9F6] px-4 py-5 sm:px-6">
                    <h2 className="font-montserrat text-base font-semibold tracking-tight text-[#262A22]">
                        Non-Toxic Kitchen Guide
                    </h2>
                    <p className="mt-2 font-body text-sm leading-relaxed text-[#555555]">
                        Audit your kitchen tools and discover which items may be leaching harmful chemicals into
                        your food.
                    </p>
                    <p className="mt-3 font-montserrat text-xs font-bold uppercase tracking-[0.08em] text-[#5A6B44]">
                        Take the Quiz — Coming soon
                    </p>
                </section>
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
