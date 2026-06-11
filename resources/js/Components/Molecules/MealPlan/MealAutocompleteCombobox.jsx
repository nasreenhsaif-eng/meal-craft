import { createPortal } from 'react-dom';
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import axios from 'axios';
import TextInput from '../../Atoms/TextInput/TextInput.jsx';
import { filterMealsForCombobox, mealCategoryBadgeLabel } from '../../../meal-library/mealSearch.ts';
import { laravelAxiosJsonHeaders } from '../../../lib/csrfToken.js';

const DEBOUNCE_MS = 300;

/**
 * Searchable meal combobox for Smart Scheduler slots.
 *
 * @param {{
 *   id?: string;
 *   label?: string;
 *   placeholder?: string;
 *   displayValue: string;
 *   mealId: number | null;
 *   categories: readonly string[];
 *   searchUrl?: string | null;
 *   meals?: import('../../../meal-library/mealSearch.ts').MealPickerOption[];
 *   onChange: (next: { displayValue: string; mealId: number | null }) => void;
 *   className?: string;
 * }} props
 */
export default function MealAutocompleteCombobox({
    id: idProp,
    label = 'Meal',
    placeholder = 'Type to search…',
    displayValue,
    mealId,
    categories,
    searchUrl = null,
    meals = [],
    onChange,
    className = '',
}) {
    const generatedId = useId();
    const inputId = idProp ?? generatedId;
    const listboxId = `${inputId}-listbox`;

    const rootRef = useRef(null);
    const abortRef = useRef(/** @type {AbortController | null} */ (null));

    const [isOpen, setIsOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const [suggestions, setSuggestions] = useState(/** @type {import('../../../meal-library/mealSearch.ts').MealPickerOption[]} */ ([]));
    const [isLoading, setIsLoading] = useState(false);
    const [searchError, setSearchError] = useState(/** @type {string | null} */ (null));
    const [menuRect, setMenuRect] = useState(/** @type {{ left: number; top: number; width: number } | null} */ (null));

    const updateMenuRect = useCallback(() => {
        const root = rootRef.current;
        if (!root) {
            return;
        }
        const input = root.querySelector('input');
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        const r = input.getBoundingClientRect();
        setMenuRect({ left: r.left, top: r.bottom, width: r.width });
    }, []);

    useEffect(() => {
        if (!isOpen) {
            setMenuRect(null);
            return undefined;
        }

        const frame = window.requestAnimationFrame(() => updateMenuRect());
        window.addEventListener('resize', updateMenuRect);
        window.addEventListener('scroll', updateMenuRect, true);

        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('resize', updateMenuRect);
            window.removeEventListener('scroll', updateMenuRect, true);
        };
    }, [displayValue, isOpen, updateMenuRect]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            const t = event.target;
            if (!(t instanceof Node)) {
                return;
            }
            const root = rootRef.current;
            if (root && !root.contains(t) && !t.closest(`[data-meal-autocomplete-menu="${inputId}"]`)) {
                setIsOpen(false);
                setHighlightedIndex(-1);
            }
        };
        document.addEventListener('mousedown', onDocMouseDown);
        return () => document.removeEventListener('mousedown', onDocMouseDown);
    }, [inputId]);

    useEffect(() => {
        const q = displayValue.trim();
        if (!isOpen || q.length < 1) {
            setSuggestions([]);
            setHighlightedIndex(-1);
            setIsLoading(false);
            setSearchError(null);
            return undefined;
        }

        if (meals.length > 0) {
            setSuggestions(filterMealsForCombobox(meals, q, categories));
            setHighlightedIndex(-1);
            setIsLoading(false);
            setSearchError(null);
            return undefined;
        }

        if (!searchUrl) {
            setSuggestions([]);
            setHighlightedIndex(-1);
            setIsLoading(false);
            setSearchError(null);
            return undefined;
        }

        setIsLoading(true);
        setSearchError(null);

        const timer = window.setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            try {
                const { data } = await axios.get(searchUrl, {
                    signal: controller.signal,
                    headers: laravelAxiosJsonHeaders(),
                    params: {
                        q,
                        categories: [...categories],
                    },
                    paramsSerializer: {
                        indexes: null,
                    },
                });
                const rows = Array.isArray(data?.meals) ? data.meals : [];
                const normalized = rows
                    .map((row) => {
                        if (!row || typeof row !== 'object') {
                            return null;
                        }
                        const id = Number(row.id);
                        const name = String(row.name ?? '').trim();
                        const category = String(row.category ?? '').trim();
                        if (!Number.isFinite(id) || !name) {
                            return null;
                        }
                        return { id, name, category };
                    })
                    .filter((row) => row !== null);
                setSuggestions(normalized);
                setHighlightedIndex(-1);
                setSearchError(null);
            } catch (error) {
                if (!axios.isCancel(error)) {
                    setSuggestions([]);
                    setSearchError('Could not load meals. Try again.');
                    // eslint-disable-next-line no-console
                    console.error('Meal scheduler search failed', error);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            }
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timer);
            abortRef.current?.abort();
        };
    }, [categories, displayValue, isOpen, meals, searchUrl]);

    const selectMeal = useCallback(
        (meal) => {
            onChange({ displayValue: meal.name, mealId: meal.id });
            setIsOpen(false);
            setHighlightedIndex(-1);
        },
        [onChange],
    );

    const handleKeyDown = (event) => {
        if (!isOpen && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            setIsOpen(true);
            updateMenuRect();
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);
            setHighlightedIndex(-1);
            return;
        }

        if (suggestions.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlightedIndex((prev) => (prev + 1) % suggestions.length);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlightedIndex((prev) => (prev <= 0 ? suggestions.length - 1 : prev - 1));
            return;
        }

        if (event.key === 'Enter' && highlightedIndex >= 0) {
            event.preventDefault();
            const meal = suggestions[highlightedIndex];
            if (meal) {
                selectMeal(meal);
            }
        }
    };

    const hasQuery = displayValue.trim().length > 0;
    const showMenu = isOpen && hasQuery && menuRect !== null;
    const emptyLabel =
        categories.length === 1 && categories[0] === 'Breakfast'
            ? 'No breakfast meals match your search.'
            : 'No meals match your search for this slot.';

    return (
        <div ref={rootRef} className={`relative min-w-0 ${className}`}>
            <TextInput
                id={inputId}
                label={label}
                placeholder={placeholder}
                value={displayValue}
                autoComplete="off"
                role="combobox"
                aria-expanded={showMenu}
                aria-controls={showMenu ? listboxId : undefined}
                aria-activedescendant={
                    showMenu && highlightedIndex >= 0 ? `${inputId}-option-${highlightedIndex}` : undefined
                }
                aria-autocomplete="list"
                onChange={(e) => {
                    const v = e.target.value;
                    onChange({ displayValue: v, mealId: null });
                    setIsOpen(true);
                    updateMenuRect();
                }}
                onFocus={() => {
                    setIsOpen(true);
                    updateMenuRect();
                }}
                onKeyDown={handleKeyDown}
                className="!max-w-none"
            />

            {showMenu
                ? createPortal(
                      <div
                          data-meal-autocomplete-menu={inputId}
                          className="fixed z-[10050]"
                          style={{
                              left: `${menuRect.left}px`,
                              top: `${menuRect.top + 8}px`,
                              width: `${menuRect.width}px`,
                          }}
                      >
                          <div
                              id={listboxId}
                              role="listbox"
                              className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl"
                          >
                              {isLoading ? (
                                  <p className="px-4 py-2 font-body text-sm text-[#555555]">Searching…</p>
                              ) : searchError ? (
                                  <p className="px-4 py-2 font-body text-sm text-status-error">{searchError}</p>
                              ) : suggestions.length === 0 ? (
                                  <p className="px-4 py-2 font-body text-sm text-[#555555]">{emptyLabel}</p>
                              ) : (
                                  <div className="max-h-56 overflow-auto">
                                      {suggestions.map((meal, index) => {
                                          const isHighlighted = index === highlightedIndex;
                                          const badge = mealCategoryBadgeLabel(meal.category);
                                          return (
                                              <button
                                                  key={meal.id}
                                                  id={`${inputId}-option-${index}`}
                                                  type="button"
                                                  role="option"
                                                  aria-selected={mealId === meal.id || isHighlighted}
                                                  className={`flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset ${
                                                      isHighlighted ? 'bg-[#F0F4EA]' : 'hover:bg-[#F8F9F6]'
                                                  }`}
                                                  onMouseEnter={() => setHighlightedIndex(index)}
                                                  onMouseDown={(e) => e.preventDefault()}
                                                  onClick={() => selectMeal(meal)}
                                              >
                                                  <span className="min-w-0 truncate">{meal.name}</span>
                                                  {badge ? (
                                                      <span className="shrink-0 rounded-full border border-[#E5E7EB] bg-[#F8F9F6] px-2 py-0.5 text-[11px] font-semibold tracking-wide text-[#555555] uppercase">
                                                          {badge}
                                                      </span>
                                                  ) : null}
                                              </button>
                                          );
                                      })}
                                  </div>
                              )}
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}
