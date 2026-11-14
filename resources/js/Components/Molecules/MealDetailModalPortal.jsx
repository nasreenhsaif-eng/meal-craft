import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import Button from '../Atoms/Button/Button.jsx';
import {
    mergeShoppingListItems,
    readCustomerShoppingList,
    removeShoppingListKeys,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import MealDetailView from './MealDetailView/MealDetailView';

/**
 * Meal plan runs inside Inertia. Crafted for YOU does not, and usePage throws there.
 *
 * @returns {number | string | null}
 */
function useOptionalInertiaUserId() {
    try {
        const page = usePage();

        return page.props?.auth?.user?.id ?? null;
    } catch {
        return null;
    }
}

/**
 * Resolve the customer phone chrome when present so details fill that screen
 * instead of the full browser viewport.
 *
 * @returns {HTMLElement}
 */
function resolveMealDetailPortalHost() {
    if (typeof document === 'undefined') {
        return null;
    }

    return (
        document.querySelector('[data-customer-onboarding-frame]') ||
        document.querySelector('[data-storybook-onboarding-frame]') ||
        document.body
    );
}

/**
 * Meal details overlay — full-screen page on mobile / phone frame (same shell as
 * SEE OTHER OPTIONS); centered sheet on larger bare viewports.
 *
 * @param {{
 *   mealDetailModal: { title: string; detailView: object } | null;
 *   onClose: () => void;
 *   loading?: boolean;
 *   hideInstructions?: boolean;
 *   enableGetRecipe?: boolean;
 *   inlineIngredients?: boolean;
 *   userId?: number | string | null;
 * }} props
 */
export default function MealDetailModalPortal({
    mealDetailModal,
    onClose,
    loading = false,
    hideInstructions = false,
    enableGetRecipe = false,
    inlineIngredients = false,
    userId = null,
}) {
    const open = Boolean(mealDetailModal) || loading;
    const [recipeOpen, setRecipeOpen] = useState(false);
    const inertiaUserId = useOptionalInertiaUserId();
    const shoppingUserId = userId ?? inertiaUserId;
    const [shoppingList, setShoppingList] = useState(() => readCustomerShoppingList(shoppingUserId));
    const shoppingCheckedKeys = useMemo(() => new Set(shoppingList.map((row) => row.key)), [shoppingList]);

    useEffect(() => {
        setShoppingList(readCustomerShoppingList(shoppingUserId));
    }, [shoppingUserId]);

    const toggleShoppingIngredient = (row, shouldAdd) => {
        setShoppingList((current) => {
            const next = shouldAdd ? mergeShoppingListItems(current, [row]) : removeShoppingListKeys(current, [row.key]);
            writeCustomerShoppingList(shoppingUserId, next);

            return next;
        });
    };

    useEffect(() => {
        if (!open) {
            setRecipeOpen(false);
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose?.();
            }
        };

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [open, onClose]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    const host = resolveMealDetailPortalHost();
    if (!host) {
        return null;
    }

    const nestedInPhoneFrame = host !== document.body;

    return createPortal(
        <div
            className={
                nestedInPhoneFrame
                    ? 'absolute inset-0 z-[120] overflow-hidden overscroll-none'
                    : 'fixed inset-0 z-[120] overflow-hidden overscroll-none sm:flex sm:items-center sm:justify-center sm:p-6'
            }
        >
            {!nestedInPhoneFrame ? (
                <button
                    type="button"
                    className="absolute inset-0 bg-black/40 max-sm:hidden"
                    aria-label="Close meal details"
                    onClick={onClose}
                />
            ) : null}
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="mc-meal-detail-modal-title"
                className={[
                    'flex w-full flex-col overflow-hidden bg-[#F8F9F6]',
                    nestedInPhoneFrame
                        ? 'absolute inset-0 rounded-none'
                        : [
                              'absolute inset-0 rounded-none',
                              'sm:relative sm:inset-auto sm:my-auto sm:h-auto sm:max-h-[92dvh] sm:max-w-3xl sm:rounded-[16px] sm:bg-white sm:shadow-2xl',
                          ].join(' '),
                ]
                    .join(' ')
                    .trim()}
            >
                <div className="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 sm:px-5 sm:py-4">
                    <h2
                        id="mc-meal-detail-modal-title"
                        className="min-w-0 flex-1 break-words font-montserrat text-lg font-bold text-[#262A22] sm:text-xl"
                    >
                        {mealDetailModal?.title ?? 'Meal details'}
                    </h2>
                    <Button
                        type="button"
                        label="Close"
                        variant="ghost"
                        size="sm"
                        onClick={onClose}
                        className="shrink-0"
                    />
                </div>
                <div className="min-h-0 flex-1 overflow-x-clip overflow-y-auto overscroll-y-contain bg-white [-webkit-overflow-scrolling:touch]">
                    {loading ? (
                        <p className="px-5 py-8 text-center font-body text-sm text-[#555555] sm:px-6">
                            Loading meal details…
                        </p>
                    ) : mealDetailModal ? (
                        <>
                            {enableGetRecipe ? (
                                <div className="border-b border-gray-100 bg-white px-4 py-3 sm:px-5">
                                    <Button
                                        type="button"
                                        label={recipeOpen ? 'Hide recipe' : 'Show recipe'}
                                        variant={recipeOpen ? 'ghost' : 'primary'}
                                        size="sm"
                                        onClick={() => setRecipeOpen((openRecipe) => !openRecipe)}
                                        className="min-w-[8.5rem]"
                                    />
                                </div>
                            ) : null}
                            <MealDetailView
                                meal={mealDetailModal.detailView}
                                hideImage={false}
                                embedded
                                hideInstructions={
                                    enableGetRecipe ? !recipeOpen : hideInstructions
                                }
                                inlineIngredients={inlineIngredients}
                                shoppingCheckedKeys={enableGetRecipe ? shoppingCheckedKeys : undefined}
                                onToggleShoppingIngredient={enableGetRecipe ? toggleShoppingIngredient : undefined}
                            />
                        </>
                    ) : null}
                </div>
            </div>
        </div>,
        host,
    );
}
