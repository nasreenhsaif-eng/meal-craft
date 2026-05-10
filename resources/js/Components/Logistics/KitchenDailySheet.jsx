import { useState } from 'react';
import PrimaryButton from '../Atoms/Button/PrimaryButton.jsx';
import { MEAL_PLAN_HEADERS, MEAL_PLAN_KEYS, displayMealCell } from './logisticsMealColumns.js';
import { buildKitchenDailySheetCsv, downloadCsv } from './logisticsCsvExport.js';
import { AllergyTagGroupCell, DislikeTagGroupCell } from './SafetyTagCells.jsx';
import Calendar from '../Molecules/Calendar/Calendar.jsx';

const MEAL_TH =
    'min-w-[7.5rem] max-w-[12rem] whitespace-normal px-2 py-2 font-sans text-[10px] font-bold uppercase leading-tight tracking-wide text-[#262A22] print:min-w-[4.5rem] print:max-w-none print:px-1 print:py-1 print:text-[9px]';

const MEAL_TD =
    'min-w-[7.5rem] max-w-[12rem] whitespace-normal break-words px-2 py-2 align-top text-[#364153] print:min-w-[4.5rem] print:max-w-none print:px-1 print:py-1 print:text-[10px] print:leading-snug';

/**
 * @param {string} iso
 */
function formatLongDate(iso) {
    if (!iso) {
        return '—';
    }
    const parts = iso.split('-').map(Number);
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        return iso;
    }
    const [y, m, d] = parts;

    return new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric' }).format(
        new Date(y, m - 1, d),
    );
}

/**
 * Printable kitchen production sheet (one row per guest / ticket).
 *
 * @param {{
 *   rows: import('./logisticsMockData.js').KitchenDailyRow[];
 *   productionDate: string;
 *   onProductionDateChange: (isoDate: string) => void;
 *   onGenerateCsvForDrive?: (csv: string) => void;
 *   printPreview?: boolean;
 *   title?: string;
 *   className?: string;
 * }} props
 */
export default function KitchenDailySheet({
    rows,
    productionDate,
    onProductionDateChange,
    onGenerateCsvForDrive,
    printPreview = false,
    title = 'Kitchen daily production sheet',
    className = '',
}) {
    const [datePickerOpen, setDatePickerOpen] = useState(false);

    const shell = printPreview
        ? 'rounded-none border-0 bg-white shadow-none print:shadow-none'
        : 'rounded-xl border border-gray-200 bg-white shadow-sm';

    const mealColumns = MEAL_PLAN_KEYS.map((key, i) => ({ key, label: MEAL_PLAN_HEADERS[i] ?? key }));

    const handleGenerateCsv = () => {
        const csv = buildKitchenDailySheetCsv(rows, productionDate);
        if (onGenerateCsvForDrive) {
            onGenerateCsvForDrive(csv);
        } else {
            downloadCsv(`kitchen-daily-${productionDate || 'undated'}.csv`, csv);
        }
    };

    return (
        <article className={`kitchen-daily-sheet font-sans ${shell} ${className}`.trim()}>
            <style>
                {`
                @media print {
                  @page { size: letter landscape; margin: 10mm; }
                  .kitchen-daily-sheet table.kitchen-daily-print-table {
                    table-layout: fixed;
                    width: 100%;
                    min-width: 0 !important;
                  }
                }
              `}
            </style>
            <header
                className={`flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 ${
                    printPreview ? 'print:border-gray-400' : ''
                }`}
            >
                <div>
                    <h2 className="m-0 font-sans text-lg font-bold tracking-tight text-[#262A22]">{title}</h2>
                    <p className="mt-1 text-sm font-medium text-[#555555]">
                        Production date:{' '}
                        <time dateTime={productionDate} className="font-semibold text-[#262A22]">
                            {productionDate ? formatLongDate(productionDate) : '—'}
                        </time>
                    </p>
                </div>
                <div className="flex flex-col items-stretch gap-2 sm:items-end">
                    <button
                        type="button"
                        onClick={() => setDatePickerOpen((o) => !o)}
                        aria-expanded={datePickerOpen}
                        className="inline-flex h-10 items-center justify-center rounded-full border-2 border-[#556C37] bg-white px-4 font-sans text-sm font-semibold text-[#556C37] shadow-sm transition-colors hover:bg-[#556C37]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                    >
                        Choose date
                    </button>
                    {datePickerOpen ? (
                        <div className="flex flex-col gap-2 rounded-lg border border-gray-200 bg-[#F9FAFB] p-3">
                            <p className="text-xs font-bold uppercase tracking-wide text-[#555555]">FILTER BY DATE</p>
                            <p className="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm font-medium leading-snug text-[#364153]">
                                <span className="shrink-0">Selected</span>
                                <span className="whitespace-nowrap font-semibold text-[#6E8C47]" aria-live="polite">
                                    {formatLongDate(productionDate)}
                                </span>
                            </p>
                            <Calendar
                                mode="single"
                                value={productionDate}
                                onChange={(iso) => onProductionDateChange(iso ?? '')}
                                defaultMonth={
                                    productionDate
                                        ? new Date(Number(productionDate.slice(0, 4)), Number(productionDate.slice(5, 7)) - 1, 1)
                                        : undefined
                                }
                                aria-label="Choose production date"
                                className="w-full max-w-none"
                            />
                        </div>
                    ) : null}
                    <PrimaryButton
                        type="button"
                        label="Generate CSV for Google Drive"
                        size="sm"
                        className="max-w-full sm:max-w-xs"
                        onClick={handleGenerateCsv}
                    />
                </div>
            </header>

            <div className="overflow-x-auto px-2 py-3 sm:px-4 print:overflow-visible print:px-1 print:py-2">
                <table className="kitchen-daily-print-table min-w-[1500px] w-full border-collapse text-left text-xs print:min-w-0 print:w-full print:text-[10px]">
                    <colgroup>
                        <col style={{ width: '8%' }} />
                        {MEAL_PLAN_KEYS.map((k) => (
                            <col key={k} style={{ width: '8.5%' }} />
                        ))}
                        <col style={{ width: '7%' }} />
                        <col style={{ width: '17%' }} />
                        <col style={{ width: '17%' }} />
                    </colgroup>
                    <thead>
                        <tr className="border-b border-gray-200 bg-[#F9FAFB] print:bg-transparent">
                            <th
                                scope="col"
                                className="whitespace-nowrap px-2 py-2 font-sans text-[10px] font-bold uppercase tracking-wide text-[#262A22] print:px-1 print:text-[9px]"
                            >
                                Name
                            </th>
                            {mealColumns.map((c) => (
                                <th key={c.key} scope="col" className={MEAL_TH}>
                                    {c.label}
                                </th>
                            ))}
                            <th
                                scope="col"
                                className="px-2 py-2 font-sans text-[10px] font-bold uppercase leading-tight tracking-wide text-[#262A22] print:px-1 print:text-[9px]"
                            >
                                Cutlery
                            </th>
                            <th
                                scope="col"
                                className="px-2 py-2 font-sans text-[10px] font-bold uppercase leading-tight tracking-wide text-[#262A22] print:px-1 print:text-[9px]"
                            >
                                Special requests
                            </th>
                            <th
                                scope="col"
                                className="px-2 py-2 font-sans text-[10px] font-bold uppercase leading-tight tracking-wide text-[#262A22] print:px-1 print:text-[9px]"
                            >
                                Allergies
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-b border-gray-100 last:border-0">
                                <td className="whitespace-normal break-words px-2 py-2 font-semibold text-[#262A22] print:px-1 print:text-[10px]">
                                    {row.name}
                                </td>
                                {MEAL_PLAN_KEYS.map((key) => (
                                    <td key={key} className={MEAL_TD}>
                                        {displayMealCell(row[key])}
                                    </td>
                                ))}
                                <td className="whitespace-normal break-words px-2 py-2 align-top text-[#364153] print:px-1 print:text-[10px]">
                                    {displayMealCell(row.cutlery)}
                                </td>
                                <DislikeTagGroupCell
                                    allergies={row.allergies}
                                    specialRequests={row.specialRequests ?? ''}
                                    kitchenPrint
                                    className="text-[#364153] print:text-[10px]"
                                />
                                <AllergyTagGroupCell
                                    allergies={row.allergies}
                                    specialRequests={row.specialRequests ?? ''}
                                    kitchenPrint
                                    className="text-[#364153] print:text-[10px]"
                                />
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </article>
    );
}
