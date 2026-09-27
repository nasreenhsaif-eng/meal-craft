import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import Button from '../Atoms/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';

/**
 * @typedef {import('../../Pages/MealPlanLibrary/dayShoppingList.js').DayShoppingIngredient} DayShoppingIngredient
 */

/**
 * @param {number} amount
 * @param {string} unit
 */
function formatAmount(amount, unit) {
    const rounded = Number.isInteger(amount) ? String(amount) : amount.toFixed(1).replace(/\.0$/, '');

    return `${rounded} ${unit}`;
}

/**
 * Modal listing a day's ingredients with checkboxes and an add-to-shopping-list action.
 *
 * @param {{
 *   open: boolean;
 *   dayLabel: string;
 *   ingredients: DayShoppingIngredient[];
 *   onClose: () => void;
 *   onAddToShoppingList: (selected: DayShoppingIngredient[]) => void;
 * }} props
 */
export default function DayShoppingListModal({
    open,
    dayLabel,
    ingredients,
    onClose,
    onAddToShoppingList,
}) {
    const allKeys = useMemo(() => ingredients.map((row) => row.key), [ingredients]);
    const [checkedKeys, setCheckedKeys] = useState(() => new Set(allKeys));
    const [addedMessage, setAddedMessage] = useState(/** @type {string | null} */ (null));

    useEffect(() => {
        if (!open) {
            return;
        }

        setCheckedKeys(new Set(ingredients.map((row) => row.key)));
        setAddedMessage(null);
    }, [open, ingredients]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    const selectedCount = checkedKeys.size;
    const allSelected = ingredients.length > 0 && selectedCount === ingredients.length;

    const toggleKey = (key) => {
        setCheckedKeys((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
        setAddedMessage(null);
    };

    const toggleAll = () => {
        setCheckedKeys(allSelected ? new Set() : new Set(allKeys));
        setAddedMessage(null);
    };

    const handleAdd = () => {
        const selected = ingredients.filter((row) => checkedKeys.has(row.key));

        if (selected.length === 0) {
            setAddedMessage('Select at least one ingredient.');

            return;
        }

        onAddToShoppingList(selected);
        setAddedMessage(
            selected.length === 1
                ? '1 ingredient added to shopping list.'
                : `${selected.length} ingredients added to shopping list.`,
        );
    };

    return createPortal(
        <div className="fixed inset-0 z-[120] flex items-end justify-center sm:items-center sm:p-4">
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                onClick={onClose}
                aria-label="Close shopping list"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="day-shopping-list-modal-title"
                className="relative z-10 flex max-h-[min(92dvh,720px)] min-h-[min(55dvh,420px)] w-full max-w-lg flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:min-h-[min(60dvh,520px)] sm:rounded-[16px]"
            >
                <div className="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                    <div className="min-w-0 flex-1">
                        <h2
                            id="day-shopping-list-modal-title"
                            className="font-montserrat text-lg font-bold text-[#262A22]"
                        >
                            Shopping list
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Ingredients for {dayLabel} selected meals
                        </p>
                    </div>
                    <Button label="Close" variant="ghost" type="button" size="sm" onClick={onClose} />
                </div>

                <div className="min-h-[12rem] flex-1 overflow-y-auto px-5 py-4">
                    {ingredients.length === 0 ? (
                        <p className="font-body text-sm text-[#555555]">
                            No ingredients yet. Select meals for this day to build a shopping list.
                        </p>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={toggleAll}
                                className="mb-3 font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44] hover:underline"
                            >
                                {allSelected ? 'Deselect all' : 'Select all'}
                            </button>
                            <ul className="space-y-1" role="list">
                                {ingredients.map((row) => {
                                    const checked = checkedKeys.has(row.key);
                                    const checkboxId = `day-shop-${row.key}`;

                                    return (
                                        <li key={row.key}>
                                            <label
                                                htmlFor={checkboxId}
                                                className="group/item flex cursor-pointer items-center gap-3 rounded-[10px] px-2 py-2.5 hover:bg-[#F8F9F6]"
                                            >
                                                <input
                                                    id={checkboxId}
                                                    type="checkbox"
                                                    className="sr-only"
                                                    checked={checked}
                                                    onChange={() => toggleKey(row.key)}
                                                />
                                                <SquareCheckbox checked={checked} presentational />
                                                <span className="min-w-0 flex-1 font-body text-sm text-[#262A22]">
                                                    {row.name}
                                                </span>
                                                <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-[#555555]">
                                                    {formatAmount(row.amount, row.unit)}
                                                </span>
                                            </label>
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    )}
                </div>

                <div className="shrink-0 border-t border-gray-100 px-5 py-4">
                    {addedMessage ? (
                        <p
                            className={`mb-3 font-body text-sm ${addedMessage.includes('at least') ? 'text-[#C44F5D]' : 'text-[#5A6B44]'}`}
                            role="status"
                        >
                            {addedMessage}
                        </p>
                    ) : null}
                    <Button
                        type="button"
                        label="Add to shopping list"
                        variant="primary"
                        size="sm"
                        className="w-full"
                        disabled={ingredients.length === 0}
                        onClick={handleAdd}
                    />
                </div>
            </div>
        </div>,
        document.body,
    );
}

/**
 * Read-only view of the accumulated shopping list for this meal plan.
 *
 * @param {{
 *   open: boolean;
 *   ingredients: DayShoppingIngredient[];
 *   onClose: () => void;
 *   onClear: () => void;
 * }} props
 */
export function SavedShoppingListModal({ open, ingredients, onClose, onClear }) {
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div className="fixed inset-0 z-[120] flex items-end justify-center sm:items-center sm:p-4">
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                onClick={onClose}
                aria-label="Close saved shopping list"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="saved-shopping-list-modal-title"
                className="relative z-10 flex max-h-[min(92dvh,720px)] min-h-[min(55dvh,420px)] w-full max-w-lg flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:min-h-[min(60dvh,520px)] sm:rounded-[16px]"
            >
                <div className="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                    <div className="min-w-0 flex-1">
                        <h2
                            id="saved-shopping-list-modal-title"
                            className="font-montserrat text-lg font-bold text-[#262A22]"
                        >
                            Saved shopping list
                        </h2>
                        <p className="mt-1 font-body text-sm text-[#555555]">
                            Ingredients added from this meal plan’s days
                        </p>
                    </div>
                    <Button label="Close" variant="ghost" type="button" size="sm" onClick={onClose} />
                </div>

                <div className="min-h-[12rem] flex-1 overflow-y-auto px-5 py-4">
                    {ingredients.length === 0 ? (
                        <p className="font-body text-sm text-[#555555]">Your shopping list is empty.</p>
                    ) : (
                        <ul className="space-y-1" role="list">
                            {ingredients.map((row) => (
                                <li
                                    key={row.key}
                                    className="flex items-center justify-between gap-3 border-b border-gray-100 px-2 py-2.5 last:border-b-0"
                                >
                                    <span className="min-w-0 flex-1 font-body text-sm text-[#262A22]">{row.name}</span>
                                    <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-[#555555]">
                                        {formatAmount(row.amount, row.unit)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="shrink-0 border-t border-gray-100 px-5 py-4">
                    <Button
                        type="button"
                        label="Clear shopping list"
                        variant="ghost"
                        size="sm"
                        className="w-full border border-[#C44F5D] text-[#C44F5D]"
                        disabled={ingredients.length === 0}
                        onClick={onClear}
                    />
                </div>
            </div>
        </div>,
        document.body,
    );
}
