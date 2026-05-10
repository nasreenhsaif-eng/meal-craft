import { useMemo } from 'react';
import PrimaryButton from '../Atoms/Button/PrimaryButton.jsx';
import { aggregateIngredientsFromChoices } from './shoppingAggregate.js';

/**
 * Shopping list derived from ingredient lines (portions × per-portion → bulk totals).
 *
 * @param {{
 *   lines: import('./logisticsMockData.js').IngredientChoiceLine[];
 *   dayLabel?: string;
 *   onGenerateCsvForDrive?: () => void;
 *   className?: string;
 * }} props
 */
export default function KitchenShoppingList({ lines, dayLabel, onGenerateCsvForDrive, className = '' }) {
    const { bulk, recipes, quantities } = useMemo(() => aggregateIngredientsFromChoices(lines), [lines]);

    return (
        <section className={`space-y-6 font-sans ${className}`.trim()}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 className="m-0 font-sans text-xl font-bold tracking-tight text-[#262A22]">Kitchen shopping list</h2>
                    {dayLabel ? (
                        <p className="mt-1 text-sm font-medium text-[#555555]">
                            Day: <span className="font-semibold text-[#262A22]">{dayLabel}</span>
                        </p>
                    ) : null}
                    <p className="mt-1 max-w-2xl text-sm leading-relaxed text-[#555555]">
                        Bulk totals combine every line (e.g. two salmon batches 10 + 3 portions at 150&nbsp;g →{' '}
                        <span className="font-semibold text-[#262A22]">1,950&nbsp;g</span>).
                    </p>
                </div>
                <PrimaryButton type="button" label="Generate CSV for Google Drive" size="sm" onClick={onGenerateCsvForDrive} />
            </div>

            <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm print:border-gray-400">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h3 className="m-0 font-sans text-sm font-bold uppercase tracking-wide text-[#262A22]">Bulk ingredients</h3>
                    <p className="mt-1 text-xs text-[#555555]">Order by vendor / walk-in — all guests combined.</p>
                </div>
                <table className="w-full min-w-[520px] border-collapse text-left text-sm">
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
                                Source lines
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {bulk.map((b) => (
                            <tr key={`${b.ingredient}-${b.unit}`} className="border-t border-gray-100">
                                <td className="px-4 py-2 font-semibold text-[#262A22]">{b.ingredient}</td>
                                <td className="px-4 py-2 text-[#364153]">{b.unit}</td>
                                <td className="px-4 py-2 font-mono text-[#364153]">{b.totalAmount.toLocaleString()}</td>
                                <td className="px-4 py-2 text-[#555555]">{b.lineCount}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm print:border-gray-400">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h3 className="m-0 font-sans text-sm font-bold uppercase tracking-wide text-[#262A22]">
                        Recipes needed for the day
                    </h3>
                    <p className="mt-1 text-xs text-[#555555]">Portion loads summed from production tickets.</p>
                </div>
                <table className="w-full min-w-[400px] border-collapse text-left text-sm">
                    <thead>
                        <tr className="bg-[#F9FAFB]">
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Recipe
                            </th>
                            <th className="px-4 py-2 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22]">
                                Portions (sum)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {recipes.map((r) => (
                            <tr key={r.label} className="border-t border-gray-100">
                                <td className="px-4 py-2 font-semibold text-[#262A22]">{r.label}</td>
                                <td className="px-4 py-2 font-mono text-[#364153]">{r.portions}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm print:border-gray-400">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h3 className="m-0 font-sans text-sm font-bold uppercase tracking-wide text-[#262A22]">Exact quantities</h3>
                    <p className="mt-1 text-xs text-[#555555]">Line-level math for audit and prep stations.</p>
                </div>
                <table className="w-full min-w-[720px] border-collapse text-left text-sm">
                    <thead>
                        <tr className="bg-[#F9FAFB]">
                            {['Recipe', 'Ingredient', 'Per portion', 'Portions', 'Line total', 'Unit'].map((h) => (
                                <th
                                    key={h}
                                    scope="col"
                                    className="whitespace-nowrap px-3 py-2 font-sans text-[10px] font-bold uppercase tracking-wide text-[#262A22]"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {quantities.map((q, i) => (
                            <tr key={`${q.recipeLabel}-${q.ingredient}-${i}`} className="border-t border-gray-100">
                                <td className="whitespace-nowrap px-3 py-2 text-[#364153]">{q.recipeLabel}</td>
                                <td className="px-3 py-2 font-medium text-[#262A22]">{q.ingredient}</td>
                                <td className="whitespace-nowrap px-3 py-2 font-mono text-[#364153]">{q.perPortion}</td>
                                <td className="whitespace-nowrap px-3 py-2 font-mono text-[#364153]">{q.portions}</td>
                                <td className="whitespace-nowrap px-3 py-2 font-mono font-semibold text-[#262A22]">
                                    {q.lineTotal.toLocaleString()}
                                </td>
                                <td className="whitespace-nowrap px-3 py-2 text-[#364153]">{q.unit}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
