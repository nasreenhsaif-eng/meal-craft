import { createPortal } from 'react-dom';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import { filterIngredientsForCombobox } from '../../meal-library/ingredientSearch.ts';

const UNIT_OPTIONS = ['g', 'kg', 'ml', 'ltr'];

/**
 * @param {{
 *   rows: { nameQuery: string; selectedName: string; ingredientId: number | null; amount: string; unit: string }[];
 *   onRowsChange: (updater: (prev: typeof rows) => typeof rows) => void;
 *   componentPickerDatabase: { id: number | null; name: string }[];
 *   activeRow: number | null;
 *   onActiveRowChange: (row: number | null) => void;
 *   suggestRect: { left: number; top: number; width: number } | null;
 *   suggestRootRef: import('react').RefObject<HTMLDivElement | null>;
 *   finishedWeightGrams: string;
 *   onFinishedWeightChange: (value: string) => void;
 *   per100Preview: { calories: number; protein: number; carbs: number; fat: number } | null;
 * }} props
 */
export default function IngredientsLibraryCompositionSection({
    rows,
    onRowsChange,
    componentPickerDatabase,
    activeRow,
    onActiveRowChange,
    suggestRect,
    suggestRootRef,
    finishedWeightGrams,
    onFinishedWeightChange,
    per100Preview,
}) {
    return (
        <div className="space-y-4">
            <TextInput
                label="Finished weight (g, optional)"
                type="number"
                placeholder="Leave blank to use sum of ingredients"
                value={finishedWeightGrams}
                onChange={(e) => onFinishedWeightChange(e.target.value)}
                className="!max-w-none"
            />
            <p className="font-body text-xs text-[#555555]">
                Enter the final weight after cooking to account for evaporation or reduction.
            </p>

            {per100Preview ? (
                <div className="rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-3">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">
                        Per 100g preview
                    </p>
                    <p className="mt-1 font-body text-sm text-[#262A22]">
                        {per100Preview.calories} kcal · P {per100Preview.protein}g · C {per100Preview.carbs}g · F{' '}
                        {per100Preview.fat}g
                    </p>
                </div>
            ) : null}

            <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="font-montserrat text-sm font-bold text-[#262A22]">Ingredient composition</p>
                    <Button
                        label="Add ingredient"
                        variant="secondary"
                        size="sm"
                        onClick={() => onRowsChange((prev) => [...prev, { nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' }])}
                    />
                </div>

                <div ref={suggestRootRef} className="mt-4 space-y-4">
                    {rows.map((row, idx) => {
                        const matches =
                            row.nameQuery.trim().length < 1
                                ? []
                                : filterIngredientsForCombobox(componentPickerDatabase, row.nameQuery, 15);

                        return (
                            <div key={idx} className="rounded-[12px] border border-gray-100 bg-[#F8F9F6] p-3">
                                <div className="grid grid-cols-12 items-end gap-4">
                                    <div className="relative col-span-12 min-w-0 lg:col-span-6">
                                        <TextInput
                                            id={`composition-combobox-${idx}`}
                                            label="Ingredient"
                                            placeholder="Type to search ingredients…"
                                            value={row.selectedName || row.nameQuery}
                                            onChange={(e) => {
                                                const v = e.target.value;
                                                onRowsChange((prev) =>
                                                    prev.map((r, i) =>
                                                        i === idx
                                                            ? { ...r, nameQuery: v, selectedName: '', ingredientId: null }
                                                            : r,
                                                    ),
                                                );
                                            }}
                                            autoComplete="off"
                                            role="combobox"
                                            aria-expanded={activeRow === idx && matches.length > 0}
                                            onFocus={() => onActiveRowChange(idx)}
                                            className="!max-w-none"
                                        />
                                        {activeRow === idx && matches.length > 0 && suggestRect
                                            ? createPortal(
                                                  <div
                                                      data-composition-ingredient-suggest
                                                      className="fixed z-[9999]"
                                                      style={{
                                                          left: `${suggestRect.left}px`,
                                                          top: `${suggestRect.top + 8}px`,
                                                          width: `${suggestRect.width}px`,
                                                      }}
                                                  >
                                                      <div
                                                          role="listbox"
                                                          className="w-full min-w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl"
                                                      >
                                                          <div className="max-h-56 overflow-x-hidden overflow-y-auto">
                                                              {matches.map((m) => (
                                                                  <button
                                                                      key={m.id != null ? `composition-ing-${m.id}` : m.name}
                                                                      type="button"
                                                                      role="option"
                                                                      className="flex w-full items-start justify-between gap-4 rounded-[12px] px-4 py-2.5 text-left font-montserrat text-sm font-bold text-[#262A22] hover:bg-[#F8F9F6]"
                                                                      onClick={() => {
                                                                          onRowsChange((prev) =>
                                                                              prev.map((r, i) =>
                                                                                  i === idx
                                                                                      ? {
                                                                                            ...r,
                                                                                            selectedName: m.name,
                                                                                            nameQuery: m.name,
                                                                                            ingredientId:
                                                                                                typeof m.id === 'number' &&
                                                                                                Number.isFinite(m.id)
                                                                                                    ? m.id
                                                                                                    : null,
                                                                                        }
                                                                                      : r,
                                                                              ),
                                                                          );
                                                                          onActiveRowChange(null);
                                                                      }}
                                                                  >
                                                                      <span className="min-w-0 flex-1 whitespace-normal break-words leading-snug">
                                                                          {m.name}
                                                                      </span>
                                                                      <span className="shrink-0 pt-0.5 text-xs font-medium whitespace-nowrap text-[#555555]">
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

                                    <div className="col-span-6 sm:col-span-3 lg:col-span-2">
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
                                        />
                                    </div>

                                    <div className="col-span-6 sm:col-span-3 lg:col-span-2">
                                        <DropdownTextInput
                                            label="Unit"
                                            value={row.unit}
                                            options={UNIT_OPTIONS}
                                            onChange={(v) =>
                                                onRowsChange((prev) => prev.map((r, i) => (i === idx ? { ...r, unit: v } : r)))
                                            }
                                            className="!max-w-none text-center"
                                        />
                                    </div>

                                    <div className="col-span-12 flex justify-end lg:col-span-2 lg:justify-center">
                                        <PillButton
                                            label="Remove"
                                            variant="outline"
                                            size="sm"
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
        </div>
    );
}
