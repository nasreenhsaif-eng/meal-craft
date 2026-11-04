import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    readCustomerFavourites,
    removeFavouriteRecipe,
    writeCustomerFavourites,
} from '../../customer/customerFavourites.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.favouritesUrl]
 * @param {string} [props.toolsUrl]
 * @param {string} [props.recipesUrl]
 */
export function FavouritesInner({
    customerName = '',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    favouritesUrl = '/app/favourites',
    toolsUrl = '/app/tools',
    recipesUrl = '/meal-plan/recipes',
}) {
    const page = usePage();
    const userId = page.props?.auth?.user?.id ?? null;
    const [favourites, setFavourites] = useState(() => readCustomerFavourites(userId));

    useEffect(() => {
        setFavourites(readCustomerFavourites(userId));
    }, [userId]);

    const handleRemove = (recipeId) => {
        setFavourites((prev) => {
            const next = removeFavouriteRecipe(prev, recipeId);
            writeCustomerFavourites(userId, next);

            return next;
        });
    };

    return (
        <CustomerInertiaShell
            customerName={customerName}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="favourites"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    favouritesUrl={favouritesUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className="w-full min-w-0">
                <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    Favourites
                </h1>
                <p className="mt-2 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Recipes you saved while cooking it yourself.
                </p>

                {favourites.length === 0 ? (
                    <div className="mt-8 rounded-[16px] border border-gray-200 bg-white px-4 py-8 text-center shadow-sm sm:px-6">
                        <p className="font-body text-sm text-[#555555]">No favourites yet.</p>
                        <Link
                            href={recipesUrl}
                            className="mt-4 inline-flex font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                        >
                            Open Cook it yourself
                        </Link>
                    </div>
                ) : (
                    <ul className="mt-6 flex flex-col gap-3" role="list">
                        {favourites.map((recipe) => {
                            const imageUrl = resolveMealImageUrl(recipe.imageUrl);

                            return (
                                <li
                                    key={recipe.id}
                                    className="flex items-center gap-3 rounded-[12px] border border-gray-200 bg-white p-3 shadow-sm"
                                >
                                    <div className="h-16 w-16 shrink-0 overflow-hidden rounded-[10px] bg-[#F8F9F6]">
                                        {imageUrl ? (
                                            <img
                                                src={imageUrl}
                                                alt=""
                                                className="h-full w-full object-cover"
                                                loading="lazy"
                                            />
                                        ) : null}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        {recipe.category ? (
                                            <p className="font-montserrat text-[10px] font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                                                {recipe.category}
                                            </p>
                                        ) : null}
                                        <p className="truncate font-montserrat text-sm font-bold text-[#262A22]">
                                            {recipe.title}
                                        </p>
                                        <Link
                                            href={recipesUrl}
                                            className="mt-1 inline-flex font-montserrat text-xs font-semibold text-[#5A6B44] hover:underline"
                                        >
                                            Open recipes
                                        </Link>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => handleRemove(recipe.id)}
                                        className="shrink-0 font-montserrat text-xs font-bold uppercase tracking-[0.08em] text-[#6B7280] hover:text-[#C44F5D]"
                                    >
                                        Remove
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function Favourites(props) {
    return <FavouritesInner {...props} />;
}

Favourites.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
