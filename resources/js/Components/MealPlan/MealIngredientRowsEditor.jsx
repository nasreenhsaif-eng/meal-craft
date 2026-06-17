import { createPortal } from 'react-dom';
import { useEffect, useRef, useState } from 'react';
import Button from '../Atoms/Button.jsx';
import PillButton from '../Atoms/Button/Button.jsx';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../Atoms/TextInput/DropdownTextInput.jsx';
import { filterIngredientsForCombobox } from '../../meal-library/ingredientSearch.ts';

const UNIT_OPTIONS = ['g', 'kg', 'ml', 'ltr'];

const EMPTY_ROW = Object.freeze({
    nameQuery: '',
    selectedName: '',
    ingredientId: null,
    amount: '100',
    unit: 'g',
});

/**
 * Ingredient row editor — search, amount, unit, add/remove (Meal Library parity).
 *
 * @param {object} props
 * @param {{ nameQuery: string; selectedName: string; ingredientId: number | null; amount: string; unit: string }[]} props.rows
 * @param {(updater: (prev: typeof props.rows) => typeof props.rows) => void} props.onRowsChange
 * @param {object[]} props.ingredientDatabase
 * @param {string} [props.comboboxIdPrefix]
 * @param {string} [props.suggestPortalAttribute]
 */
export default function MealIngredientRowsEditor({
    rows,
    onRowsChange,
    ingredientDatabase,
    comboboxIdPrefix = 'meal-ingredient-combobox',
    suggestPortalAttribute = 'data-meal-ingredient-suggest',
}) {
    const suggestRootRef = useRef(/** @type {HTMLDivElement | null} */ (null));
    const [activeSuggestRow, setActiveSuggestRow] = useState(/** @type {number | null} */ (null));
    const [suggestRect, setSuggestRect] = useState(
        /** @type {{ left: number; top: number; width: number } | null} */ (null),
    );

    useEffect(() => {
        if (typeof document === 'undefined') {
            return undefined;
        }

        const onDocMouseDown = (event) => {
            const root = suggestRootRef.current;
            if (!root) {
                return;
            }
            const target = event.target;
            if (!(target instanceof Node)) {
                return;
            }
            if (root.contains(target)) {
                return;
            }
            if (target.closest(`[${suggestPortalAttribute}]`)) {
                return;
            }
            setActiveSuggestRow(null);
        };

        document.addEventListener('mousedown', onDocMouseDown);
        return () => document.removeEventListener('mousedown', onDocMouseDown);
    }, [suggestPortalAttribute]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }
        if (activeSuggestRow === null) {
            setSuggestRect(null);
            return undefined;
        }

        const updateRect = () => {
            const el = document.getElementById(`${comboboxIdPrefix}-${activeSuggestRow}`);
            if (!el) {
                return;
            }
            const rect = el.getBoundingClientRect();
            setSuggestRect({ left: rect.left, top: rect.bottom, width: rect.width });
        };

        updateRect();
        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);

        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [activeSuggestRow, comboboxIdPrefix]);

    return (
        <div className="rounded-[12px] border border-gray-200 bg-white p-4">
            <div className="flex items-center justify-between gap-3">
                <p className="font-montserrat text-sm font-bold text-[#262A22]">Ingredients</p>
                <Button
                    label="Add ingredient"
                    variant="secondary"
                    size="sm"
                    type="button"
                    onClick={() => onRowsChange((prev) => [...prev, { ...EMPTY_ROW }])}
                />
            </div>
            <p className="mt-1 font-body text-xs text-[#555555]">
                Search the ingredient library, adjust amounts, or remove rows — same as Meal Library edit.
            </p>

            <div ref={suggestRootRef} className="mt-4 space-y-4">
                {rows.map((row, idx) => {
                    const matches =
                        row.nameQuery.trim().length < 1
                            ? []
                            : filterIngredientsForCombobox(ingredientDatabase, row.nameQuery, 15);

                    return (
                        <div key={idx} className="rounded-[12px] border border-gray-100 bg-[#F8F9F6] p-3">
                            <div className="grid gap-4 md:grid-cols-[1fr_100px_90px_auto] md:items-end">
                                <div className="relative min-w-0">
                                    <TextInput
                                        id={`${comboboxIdPrefix}-${idx}`}
                                        label="Ingredient"
                                        placeholder="Type to search…"
                                        value={row.selectedName || row.nameQuery}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            onRowsChange((prev) =>
                                                prev.map((r, i) =>
                                                    i === idx
                                                        ? { ...r, nameQuery: value, selectedName: '', ingredientId: null }
                                                        : r,
                                                ),
                                            );
                                        }}
                                        autoComplete="off"
                                        role="combobox"
                                        aria-expanded={activeSuggestRow === idx && matches.length > 0}
                                        aria-controls={matches.length > 0 ? `${comboboxIdPrefix}-listbox-${idx}` : undefined}
                                        aria-autocomplete="list"
                                        onFocus={() => setActiveSuggestRow(idx)}
                                        className="!max-w-none"
                                    />
                                    {activeSuggestRow === idx && matches.length > 0 && suggestRect
                                        ? createPortal(
                                              <div
                                                  {...{ [suggestPortalAttribute]: true }}
                                                  className="fixed z-[9999]"
                                                  style={{
                                                      left: `${suggestRect.left}px`,
                                                      top: `${suggestRect.top + 8}px`,
                                                      width: `${suggestRect.width}px`,
                                                  }}
                                              >
                                                  <div
                                                      id={`${comboboxIdPrefix}-listbox-${idx}`}
                                                      role="listbox"
                                                      className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl"
                                                  >
                                                      <div className="max-h-56 overflow-auto">
                                                          {matches.map((match) => (
                                                              <button
                                                                  key={match.id != null ? `ing-${match.id}` : match.name}
                                                                  type="button"
                                                                  role="option"
                                                                  className="flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors hover:bg-[#F8F9F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset"
                                                                  onClick={() => {
                                                                      onRowsChange((prev) =>
                                                                          prev.map((r, i) =>
                                                                              i === idx
                                                                                  ? {
                                                                                        ...r,
                                                                                        selectedName: match.name,
                                                                                        nameQuery: match.name,
                                                                                        ingredientId: (() => {
                                                                                            const rawId = match.id;
                                                                                            if (
                                                                                                typeof rawId ===
                                                                                                    'number' &&
                                                                                                Number.isFinite(rawId)
                                                                                            ) {
                                                                                                return rawId;
                                                                                            }
                                                                                            const parsed = Number(rawId);
                                                                                            return Number.isFinite(
                                                                                                parsed,
                                                                                            ) && parsed > 0
                                                                                                ? parsed
                                                                                                : null;
                                                                                        })(),
                                                                                    }
                                                                                  : r,
                                                                          ),
                                                                      );
                                                                      setActiveSuggestRow(null);
                                                                  }}
                                                              >
                                                                  <span className="min-w-0 truncate">{match.name}</span>
                                                                  <span className="shrink-0 text-xs font-medium text-[#555555]">
                                                                      per 100g
                                                                  </span>
                                                              </button>
                                                          ))}
                                                      </div>
                                                  </div>
                                              </div>,
                                              document.body,
                                          )
                                        : null}
                                </div>

                                <TextInput
                                    label="Amount"
                                    type="number"
                                    placeholder="100"
                                    value={row.amount}
                                    onChange={(e) =>
                                        onRowsChange((prev) =>
                                            prev.map((r, i) => (i === idx ? { ...r, amount: e.target.value } : r)),
                                        )
                                    }
                                    className="!max-w-none text-center"
                                    inputMode="decimal"
                                />

                                <DropdownTextInput
                                    label="Unit"
                                    value={row.unit}
                                    options={UNIT_OPTIONS}
                                    onChange={(value) =>
                                        onRowsChange((prev) => prev.map((r, i) => (i === idx ? { ...r, unit: value } : r)))
                                    }
                                    className="!max-w-none text-center"
                                />

                                <div className="flex justify-end">
                                    <PillButton
                                        label="Remove"
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                        onClick={() =>
                                            onRowsChange((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)))
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
