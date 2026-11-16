import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import PrimaryButton from '../Atoms/Button/PrimaryButton.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import MealCraftLogo from '../Atoms/Logo/MealCraftLogo.jsx';
import { mealInstructionStepsForDisplay } from '../../meal-library/mealInstructionsDisplay.ts';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';
import {
    aggregateBulkIngredientsFromRecipeBatches,
    aggregateKitchenBaseRecipeBatches,
    aggregateKitchenRecipeBatches,
    mergeKitchenRecipeBatches,
    scaleKitchenRecipeBatchIngredients,
} from './kitchenRecipeBatches.js';

/**
 * Wait until every <img> in the root has finished loading (or errored).
 *
 * @param {HTMLElement | null} root
 * @returns {Promise<void>}
 */
function waitForImages(root) {
    if (!root) {
        return Promise.resolve();
    }

    const images = [...root.querySelectorAll('img')];
    if (images.length === 0) {
        return Promise.resolve();
    }

    return Promise.all(
        images.map(
            (image) =>
                new Promise((resolve) => {
                    if (image.complete) {
                        resolve();
                        return;
                    }

                    const done = () => {
                        image.removeEventListener('load', done);
                        image.removeEventListener('error', done);
                        resolve();
                    };

                    image.addEventListener('load', done);
                    image.addEventListener('error', done);
                }),
        ),
    ).then(() => undefined);
}

/**
 * Kitchen operation sheets — recipes needed first, then bulk totals from those recipes.
 *
 * @param {{
 *   lines?: import('./logisticsMockData.js').IngredientChoiceLine[];
 *   adaptedMeals?: import('./kitchenRecipeBatches.js').AdaptedKitchenMeal[];
 *   baseRecipes?: import('./kitchenRecipeBatches.js').KitchenBaseRecipe[];
 *   dayLabel?: string;
 *   onGenerateCsvForDrive?: () => void;
 *   className?: string;
 * }} props
 */
export default function KitchenShoppingList({
    adaptedMeals = [],
    baseRecipes = [],
    dayLabel,
    onGenerateCsvForDrive,
    className = '',
}) {
    const mealBatches = useMemo(() => aggregateKitchenRecipeBatches(adaptedMeals), [adaptedMeals]);
    const baseBatches = useMemo(
        () => aggregateKitchenBaseRecipeBatches(baseRecipes),
        [baseRecipes],
    );
    const recipeBatches = useMemo(
        () => mergeKitchenRecipeBatches(mealBatches, baseBatches),
        [mealBatches, baseBatches],
    );
    const bulk = useMemo(
        () => aggregateBulkIngredientsFromRecipeBatches(mealBatches, baseRecipes),
        [mealBatches, baseRecipes],
    );

    const [selectedKeys, setSelectedKeys] = useState(() => new Set());
    const [bulkSearchQuery, setBulkSearchQuery] = useState('');
    const [printBatches, setPrintBatches] = useState(
        /** @type {import('./kitchenRecipeBatches.js').KitchenRecipeBatch[]} */ ([]),
    );
    const printPackRef = useRef(/** @type {HTMLDivElement | null} */ (null));

    const filteredBulk = useMemo(() => {
        const query = bulkSearchQuery.trim().toLowerCase();

        if (query === '') {
            return bulk;
        }

        return bulk.filter((row) => {
            const ingredient = String(row.ingredient ?? '').toLowerCase();
            const usedIn = (row.usedInRecipes ?? []).join(' ').toLowerCase();

            return ingredient.includes(query) || usedIn.includes(query);
        });
    }, [bulk, bulkSearchQuery]);

    useEffect(() => {
        setSelectedKeys((prev) => {
            const valid = new Set(recipeBatches.map((batch) => batch.key));
            const next = new Set([...prev].filter((key) => valid.has(key)));

            return next.size === prev.size ? prev : next;
        });
    }, [recipeBatches]);

    useEffect(() => {
        if (printBatches.length === 0) {
            return undefined;
        }

        let cancelled = false;
        document.body.classList.add('kitchen-printing-recipe-pack');

        const handleAfterPrint = () => {
            document.body.classList.remove('kitchen-printing-recipe-pack');
            setPrintBatches([]);
        };
        window.addEventListener('afterprint', handleAfterPrint);

        const runPrint = async () => {
            // Allow the off-screen pack (and images) to mount before printing.
            await new Promise((resolve) => window.requestAnimationFrame(() => resolve(undefined)));
            await waitForImages(printPackRef.current);
            if (cancelled) {
                return;
            }
            window.print();
        };

        runPrint();

        return () => {
            cancelled = true;
            window.removeEventListener('afterprint', handleAfterPrint);
            document.body.classList.remove('kitchen-printing-recipe-pack');
        };
    }, [printBatches]);

    const allSelected = recipeBatches.length > 0 && recipeBatches.every((batch) => selectedKeys.has(batch.key));
    const selectedCount = selectedKeys.size;

    const toggleAll = () => {
        if (allSelected) {
            setSelectedKeys(new Set());
            return;
        }

        setSelectedKeys(new Set(recipeBatches.map((batch) => batch.key)));
    };

    const toggleKey = (key) => {
        setSelectedKeys((prev) => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    const handleDownloadSelected = () => {
        const selected = recipeBatches.filter((batch) => selectedKeys.has(batch.key));
        if (selected.length === 0) {
            return;
        }

        setPrintBatches(selected);
    };

    return (
        <section className={`space-y-6 font-sans ${className}`.trim()}>
            <div className={`flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between ${printBatches.length > 0 ? 'print:hidden' : ''}`}>
                <div>
                    <h2 className="m-0 font-sans text-xl font-bold tracking-tight text-[#262A22]">
                        Kitchen operation sheets
                    </h2>
                    {dayLabel ? (
                        <p className="mt-1 text-sm font-medium text-[#555555]">
                            Day: <span className="font-semibold text-[#262A22]">{dayLabel}</span>
                        </p>
                    ) : null}
                    <p className="mt-1 max-w-2xl text-sm leading-relaxed text-[#555555]">
                        Select recipes to download scaled prep packs. Bulk totals sum ingredients across every recipe
                        on this list (portions × per-plate grams).
                    </p>
                </div>
                <PrimaryButton type="button" label="Generate CSV for Google Drive" size="sm" onClick={onGenerateCsvForDrive} />
            </div>

            <div className={`overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm print:hidden print:border-gray-400`}>
                <div className="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 className="m-0 font-sans text-sm font-bold uppercase tracking-wide text-[#262A22]">
                            Recipes needed for the day
                        </h3>
                        <p className="mt-1 text-xs text-[#555555]">
                            Meals from customer craft selections, plus prepared base recipes needed that day.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <PrimaryButton
                            type="button"
                            label={
                                selectedCount > 0
                                    ? `Download selected recipes (${selectedCount})`
                                    : 'Download selected recipes'
                            }
                            size="sm"
                            disabled={selectedCount === 0}
                            onClick={handleDownloadSelected}
                        />
                    </div>
                </div>
                <table className="w-full min-w-[420px] border-collapse text-left text-sm">
                    <thead>
                        <tr className="bg-[#F9FAFB]">
                            <th className="w-12 px-4 py-2 align-middle">
                                <button
                                    type="button"
                                    onClick={toggleAll}
                                    disabled={recipeBatches.length === 0}
                                    aria-label={allSelected ? 'Deselect all recipes' : 'Select all recipes'}
                                    title={allSelected ? 'Deselect all recipes' : 'Select all recipes'}
                                    className="inline-flex items-center justify-center disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <SquareCheckbox
                                        checked={allSelected}
                                        presentational
                                        className={recipeBatches.length === 0 ? 'opacity-40' : ''}
                                    />
                                </button>
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Recipe
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Type
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Portions / amount
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {recipeBatches.length === 0 ? (
                            <tr className="border-t border-gray-100">
                                <td colSpan={4} className="px-4 py-6 text-sm text-[#555555]">
                                    No adapted recipes for this day yet.
                                </td>
                            </tr>
                        ) : (
                            recipeBatches.map((batch) => {
                                const checked = selectedKeys.has(batch.key);

                                return (
                                    <tr key={batch.key} className="border-t border-gray-100">
                                        <td className="px-4 py-2 align-middle">
                                            <SquareCheckbox checked={checked} onChange={() => toggleKey(batch.key)} />
                                        </td>
                                        <td className="px-4 py-2 font-semibold text-[#262A22]">{batch.mealName}</td>
                                        <td className="px-4 py-2 text-[#555555]">
                                            {batch.typeLabel ?? (batch.kind === 'base' ? 'base recipe' : 'meal')}
                                        </td>
                                        <td className="px-4 py-2 font-mono text-[#364153]">
                                            {batch.portionsLabel ?? batch.portions}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm print:hidden print:border-gray-400">
                <div className="space-y-3 border-b border-gray-100 px-4 py-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 className="m-0 font-sans text-sm font-bold uppercase tracking-wide text-[#262A22]">
                            Bulk ingredients
                        </h3>
                        <PrimaryButton
                            type="button"
                            label="Generate CSV for Google Drive"
                            size="sm"
                            onClick={onGenerateCsvForDrive}
                        />
                    </div>
                    <label className="flex w-full max-w-2xl flex-col gap-1">
                        <span className="text-[10px] font-bold uppercase tracking-wide text-[#555555]">
                            Filter ingredients
                        </span>
                        <input
                            type="search"
                            value={bulkSearchQuery}
                            onChange={(event) => setBulkSearchQuery(event.target.value)}
                            placeholder="Search ingredient or recipe…"
                            aria-label="Filter bulk ingredients"
                            className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 font-sans text-sm text-[#262A22] outline-none placeholder:text-[#9CA3AF] focus:border-[#6E8C47] focus:ring-2 focus:ring-[#6E8C47]/30"
                        />
                    </label>
                </div>
                <table className="w-full min-w-[640px] border-collapse text-left text-sm">
                    <thead>
                        <tr className="bg-[#F9FAFB]">
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Ingredient
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Unit
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Total needed
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Used in
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {bulk.length === 0 ? (
                            <tr className="border-t border-gray-100">
                                <td colSpan={4} className="px-4 py-6 text-sm text-[#555555]">
                                    No recipe ingredients to aggregate for this day yet.
                                </td>
                            </tr>
                        ) : filteredBulk.length === 0 ? (
                            <tr className="border-t border-gray-100">
                                <td colSpan={4} className="px-4 py-6 text-sm text-[#555555]">
                                    No ingredients match “{bulkSearchQuery.trim()}”.
                                </td>
                            </tr>
                        ) : (
                            filteredBulk.map((b) => (
                                <tr key={`${b.ingredient}-${b.unit}`} className="border-t border-gray-100">
                                    <td className="px-4 py-2 font-semibold text-[#262A22]">{b.ingredient}</td>
                                    <td className="px-4 py-2 text-[#364153]">{b.unit}</td>
                                    <td className="px-4 py-2 font-mono text-[#364153]">
                                        {b.totalAmount.toLocaleString()}
                                    </td>
                                    <td className="max-w-[18rem] px-4 py-2 text-[#555555]">
                                        {(b.usedInRecipes ?? []).join(', ') || '—'}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {printBatches.length > 0 && typeof document !== 'undefined'
                ? createPortal(
                      <div
                          ref={printPackRef}
                          className="kitchen-recipe-print-pack fixed left-[-10000px] top-0 z-[200] w-[7.5in] bg-white print:static print:left-auto print:top-auto print:z-auto print:w-full"
                          aria-hidden="true"
                      >
                          <style>
                              {`
                        @media print {
                          @page { size: letter portrait; margin: 12mm; }
                          body.kitchen-printing-recipe-pack * {
                            visibility: hidden !important;
                          }
                          body.kitchen-printing-recipe-pack .kitchen-recipe-print-pack,
                          body.kitchen-printing-recipe-pack .kitchen-recipe-print-pack * {
                            visibility: visible !important;
                          }
                          body.kitchen-printing-recipe-pack .kitchen-recipe-print-pack {
                            position: absolute !important;
                            left: 0 !important;
                            top: 0 !important;
                            width: 100% !important;
                          }
                          .kitchen-recipe-print-card {
                            break-after: page;
                            page-break-after: always;
                          }
                          .kitchen-recipe-print-card:last-child {
                            break-after: auto;
                            page-break-after: auto;
                          }
                          .kitchen-recipe-print-image {
                            max-height: 3.5in;
                            width: 100%;
                            object-fit: cover;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                          }
                        }
                      `}
                          </style>
                          {printBatches.map((batch) => {
                              const ingredients = scaleKitchenRecipeBatchIngredients(batch);
                              const steps = mealInstructionStepsForDisplay(batch.instructions);
                              const imageUrl = resolveMealImageUrl(batch.imageUrl);

                              return (
                                  <article
                                      key={batch.key}
                                      className="kitchen-recipe-print-card mx-auto max-w-[7.5in] space-y-5 pb-8 font-sans text-[#262A22]"
                                  >
                                      <header className="space-y-1 border-b border-gray-200 pb-3">
                                          <h2 className="m-0 text-2xl font-bold tracking-tight">{batch.mealName}</h2>
                                          <p className="m-0 text-sm font-semibold text-[#556C37]">
                                              {batch.kind === 'base'
                                                  ? `Base recipe · ${batch.portionsLabel ?? `${Math.round(Number(batch.totalGramsNeeded ?? 0))}g`} total`
                                                  : `Scaled to ${batch.portions} ${batch.portions === 1 ? 'serving' : 'servings'}`}
                                          </p>
                                          {dayLabel ? (
                                              <p className="m-0 text-xs text-[#555555]">
                                                  Production day: {dayLabel}
                                              </p>
                                          ) : null}
                                      </header>

                                      <div className="overflow-hidden rounded-lg border border-gray-200 bg-[#F8F9F6]">
                                          {imageUrl ? (
                                              <img
                                                  src={imageUrl}
                                                  alt={batch.mealName}
                                                  className="kitchen-recipe-print-image mx-auto block max-h-64 w-full object-cover"
                                              />
                                          ) : (
                                              <div className="flex min-h-[12rem] w-full items-center justify-center">
                                                  <MealCraftLogo
                                                      variant="seal-sm"
                                                      width={72}
                                                      className="opacity-70"
                                                      alt=""
                                                  />
                                              </div>
                                          )}
                                      </div>

                                      <section>
                                          <h3 className="m-0 mb-2 text-sm font-bold uppercase tracking-wide">
                                              Ingredients
                                          </h3>
                                          <ul className="m-0 list-disc space-y-1 pl-5 text-sm leading-relaxed">
                                              {ingredients.map((ingredient) => (
                                                  <li key={`${batch.key}-${ingredient.id}-${ingredient.name}`}>
                                                      {ingredient.displayLine}
                                                  </li>
                                              ))}
                                          </ul>
                                      </section>

                                      <section>
                                          <h3 className="m-0 mb-2 text-sm font-bold uppercase tracking-wide">
                                              Instructions
                                          </h3>
                                          <ol className="m-0 list-decimal space-y-2 pl-5 text-sm leading-relaxed">
                                              {steps.map((step, index) => (
                                                  <li key={`${batch.key}-step-${index}`}>{step}</li>
                                              ))}
                                          </ol>
                                      </section>
                                  </article>
                              );
                          })}
                      </div>,
                      document.body,
                  )
                : null}
        </section>
    );
}
