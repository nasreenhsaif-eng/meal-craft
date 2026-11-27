import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button.jsx';
import CustomerMealCard from '../../Components/CustomerMealCard.jsx';
import { listDayMealsWithHighlights } from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import CookMealCard from '../../Components/Molecules/Customer/CookMealCard.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    mealFromFavouriteRecipe,
    readCustomerFavourites,
    removeFavouriteRecipe,
    writeCustomerFavourites,
} from '../../customer/customerFavourites.js';
import {
    mergeShoppingListItems,
    readCustomerShoppingList,
    removeShoppingListKeys,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';

/**
 * @param {object | null | undefined} craftPlan
 * @returns {Map<string, object>}
 */
function mealsByIdFromCraftPlan(craftPlan) {
    /** @type {Map<string, object>} */
    const byId = new Map();

    for (const day of craftPlan?.days ?? []) {
        for (const meal of listDayMealsWithHighlights(day?.categories ?? {})) {
            const id = String(meal.id);

            if (!byId.has(id)) {
                byId.set(id, meal);
            }
        }
    }

    return byId;
}

/**
 * @param {object} props
 */
export function FavouritesInner({
    customerName = '',
    craftPlan = null,
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    toolsUrl = '/app/tools',
    favouritesUrl = '/app/favourites',
    cartUrl = '/app/cart',
    recipesUrl = '/meal-plan/recipes',
}) {
    const page = usePage();
    const userId = page.props?.auth?.user?.id ?? null;
    const [shoppingList, setShoppingList] = useState(() => readCustomerShoppingList(userId));
    const [favourites, setFavourites] = useState(() => readCustomerFavourites(userId));
    const [selectedMealId, setSelectedMealId] = useState(/** @type {string | null} */ (null));

    useEffect(() => {
        setShoppingList(readCustomerShoppingList(userId));
        setFavourites(readCustomerFavourites(userId));
    }, [userId]);

    const liveMeals = useMemo(() => mealsByIdFromCraftPlan(craftPlan), [craftPlan]);

    const savedMeals = useMemo(
        () =>
            favourites.map((recipe) => {
                const live = liveMeals.get(recipe.id);

                return live ?? mealFromFavouriteRecipe(recipe);
            }),
        [favourites, liveMeals],
    );

    const selectedMeal = useMemo(
        () => savedMeals.find((meal) => String(meal.id) === String(selectedMealId)) ?? null,
        [savedMeals, selectedMealId],
    );

    useLayoutEffect(() => {
        window.scrollTo(0, 0);
    }, [selectedMealId]);

    const handleToggleIngredient = useCallback(
        (rows, shouldAdd) => {
            setShoppingList((prev) => {
                const next = shouldAdd
                    ? mergeShoppingListItems(prev, rows)
                    : removeShoppingListKeys(
                          prev,
                          rows.map((row) => row.key),
                      );
                writeCustomerShoppingList(userId, next);

                return next;
            });
        },
        [userId],
    );

    const handleRemoveFavourite = useCallback(
        (recipeId) => {
            setFavourites((prev) => {
                const next = removeFavouriteRecipe(prev, recipeId);
                writeCustomerFavourites(userId, next);

                return next;
            });
            setSelectedMealId((current) => (String(current) === String(recipeId) ? null : current));
        },
        [userId],
    );

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="favourites"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    toolsUrl={toolsUrl}
                    favouritesUrl={favouritesUrl}
                />
            }
        >
            {selectedMeal != null ? (
                <div className="w-full min-w-0">
                    <div className="mb-3 flex items-start justify-between gap-3">
                        <h1 className="font-montserrat text-xl font-bold tracking-tight text-heading sm:text-2xl">
                            Recipe details
                        </h1>
                        <Button
                            type="button"
                            label="Back"
                            variant="ghost"
                            size="sm"
                            onClick={() => setSelectedMealId(null)}
                            className="shrink-0"
                        />
                    </div>
                    <CookMealCard
                        meal={selectedMeal}
                        shoppingList={shoppingList}
                        onToggleIngredient={handleToggleIngredient}
                        favourited
                        onToggleFavourite={() => handleRemoveFavourite(selectedMeal.id)}
                    />
                </div>
            ) : (
                <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
                    <h1 className="font-montserrat text-lg font-bold tracking-tight text-heading">Favourites</h1>
                    <p className="mt-1 font-body text-sm text-body">
                        Recipes you favourited while cooking it yourself.
                    </p>

                    {savedMeals.length === 0 ? (
                        <div className="mt-4 text-center">
                            <p className="font-body text-sm text-body">No favourites yet.</p>
                            <Link
                                href={recipesUrl}
                                className="mt-4 inline-flex font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                            >
                                Open Cook it yourself
                            </Link>
                        </div>
                    ) : (
                        <ul className="mt-4 flex flex-wrap justify-center gap-4" role="list">
                            {savedMeals.map((meal) => {
                                const imageUrl = resolveMealImageUrl(meal.imageUrl) || undefined;

                                return (
                                    <li key={meal.id} className="shrink-0">
                                        <CustomerMealCard
                                            deck
                                            alignActionsBottom
                                            hideCraftButton
                                            title={meal.title}
                                            imageUrl={imageUrl}
                                            macros={meal.macros}
                                            onViewDetails={() => setSelectedMealId(String(meal.id))}
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            )}
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
