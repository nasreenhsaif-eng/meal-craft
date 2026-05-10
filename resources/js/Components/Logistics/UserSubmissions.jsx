import { MEAL_PLAN_HEADERS, MEAL_PLAN_KEYS, displayMealCell } from './logisticsMealColumns.js';
import { buildUserSubmissionsCsv, downloadCsv } from './logisticsCsvExport.js';
import { AllergyTagGroupCell, DislikeTagGroupCell } from './SafetyTagCells.jsx';
import Calendar from '../Molecules/Calendar/Calendar.jsx';

const MEAL_CELL =
    'min-w-[7.5rem] max-w-[11rem] whitespace-normal break-words px-3 py-2 align-top text-[#364153] print:min-w-0 print:max-w-none print:px-1.5 print:py-1 print:text-[10px] print:leading-snug';

function formatSubmittedAt(iso) {
    try {
        const d = new Date(iso);
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(d);
    } catch {
        return iso;
    }
}

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
 * High-density submissions grid with horizontal scroll.
 *
 * @param {{
 *   submissions: import('./logisticsMockData.js').UserSubmissionRow[];
 *   selectedDate: string;
 *   onDateChange: (value: string) => void;
 *   onPrint?: () => void;
 *   onSendToGoogleDrive?: (csv: string) => void;
 *   onExportCsv?: (csv: string) => void;
 *   className?: string;
 * }} props
 */
export default function UserSubmissions({
    submissions,
    selectedDate,
    onDateChange,
    onPrint,
    onSendToGoogleDrive,
    onExportCsv,
    className = '',
}) {
    const filtered = selectedDate
        ? submissions.filter((s) => s.submittedAt.slice(0, 10) === selectedDate)
        : submissions;

    const driveCsv = () => buildUserSubmissionsCsv(filtered);

    const handleSendToGoogleDrive = () => {
        const csv = driveCsv();
        if (onSendToGoogleDrive) {
            onSendToGoogleDrive(csv);
        } else {
            downloadCsv(`user-submissions-google-drive-${selectedDate || 'all'}.csv`, csv);
        }
    };

    const handleExportCsv = () => {
        const csv = driveCsv();
        if (onExportCsv) {
            onExportCsv(csv);
        } else {
            downloadCsv(`user-submissions-export-${selectedDate || 'all'}.csv`, csv);
        }
    };

    const baseHeaders = ['Submission date', 'Name', 'Craft', 'Plan'];
    const tableHeaders = [...baseHeaders, ...MEAL_PLAN_HEADERS, 'Allergies', 'Special requests', 'Submission ID'];

    return (
        <section className={`font-sans ${className}`.trim()}>
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="flex flex-col gap-1">
                    <p className="text-xs font-bold uppercase tracking-wide text-[#555555]">FILTER BY DATE</p>
                    <p className="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm font-medium leading-snug text-[#364153]">
                        <span className="shrink-0">Selected</span>
                        <span className="whitespace-nowrap font-semibold text-[#6E8C47]" aria-live="polite">
                            {formatLongDate(selectedDate)}
                        </span>
                    </p>
                    <Calendar
                        mode="single"
                        value={selectedDate}
                        onChange={(iso) => onDateChange(iso ?? '')}
                        defaultMonth={selectedDate ? new Date(Number(selectedDate.slice(0, 4)), Number(selectedDate.slice(5, 7)) - 1, 1) : undefined}
                        aria-label="Filter submissions by date"
                        className="w-full max-w-none"
                    />
                </div>
                <div className="flex flex-wrap gap-2" role="group" aria-label="Export actions">
                    <button
                        type="button"
                        onClick={onPrint}
                        className="inline-flex h-10 items-center justify-center rounded-full border-2 border-[#556C37] bg-white px-4 font-sans text-sm font-semibold text-[#556C37] shadow-sm transition-colors hover:bg-[#556C37]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                    >
                        Print
                    </button>
                    <button
                        type="button"
                        onClick={handleSendToGoogleDrive}
                        className="inline-flex h-10 items-center justify-center rounded-full border-2 border-[#556C37] bg-white px-4 font-sans text-sm font-semibold text-[#556C37] shadow-sm transition-colors hover:bg-[#556C37]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                    >
                        Send to Google Drive
                    </button>
                    <button
                        type="button"
                        onClick={handleExportCsv}
                        className="inline-flex h-10 items-center justify-center rounded-full border-2 border-[#556C37] bg-white px-4 font-sans text-sm font-semibold text-[#556C37] shadow-sm transition-colors hover:bg-[#556C37]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                    >
                        Export CSV
                    </button>
                </div>
            </div>

            <div
                tabIndex={0}
                role="region"
                aria-label="User Submissions Table"
                className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2 print:overflow-visible print:border-gray-400"
            >
                <table className="min-w-[1780px] w-full border-collapse text-left text-sm print:min-w-full print:table-fixed">
                    <thead>
                        <tr className="border-b border-gray-200 bg-[#F9FAFB]">
                            {tableHeaders.map((h) => (
                                <th
                                    key={h}
                                    scope="col"
                                    className="px-3 py-2.5 font-sans text-xs font-bold uppercase tracking-wide text-[#262A22] print:px-1.5 print:py-1.5 print:text-[9px]"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((row) => (
                            <tr key={row.id} className="border-b border-gray-100 last:border-0 hover:bg-gray-50/80">
                                <td className="whitespace-nowrap px-3 py-2 font-medium text-[#364153] print:whitespace-normal print:px-1.5 print:text-[10px]">
                                    {formatSubmittedAt(row.submittedAt)}
                                </td>
                                <td className="whitespace-nowrap px-3 py-2 font-semibold text-[#262A22] print:px-1.5 print:text-[10px]">
                                    {row.name}
                                </td>
                                <td className="whitespace-nowrap px-3 py-2 text-[#364153] print:px-1.5 print:text-[10px]">{row.craft}</td>
                                <td className="min-w-[7rem] whitespace-normal px-3 py-2 text-[#364153] print:px-1.5 print:text-[10px]">
                                    {row.plan}
                                </td>
                                {MEAL_PLAN_KEYS.map((key) => (
                                    <td key={key} className={MEAL_CELL}>
                                        {displayMealCell(row[key])}
                                    </td>
                                ))}
                                <AllergyTagGroupCell
                                    allergyNotes={row.allergyNotes}
                                    specialRequests={row.specialRequests ?? ''}
                                    className="text-[#364153] print:text-[10px]"
                                />
                                <DislikeTagGroupCell
                                    allergyNotes={row.allergyNotes}
                                    specialRequests={row.specialRequests ?? ''}
                                    className="text-[#364153] print:text-[10px]"
                                />
                                <td className="whitespace-nowrap px-3 py-2 font-mono text-xs text-[#555555] print:px-1.5 print:text-[9px]">
                                    {row.submissionId}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="mt-2 text-xs font-medium text-[#555555]">
                Showing <span className="font-semibold text-[#262A22]">{filtered.length}</span> of{' '}
                <span className="font-semibold text-[#262A22]">{submissions.length}</span> submissions
                {selectedDate ? ` for ${selectedDate}` : ''}. CSV / Drive exports use the same headers as the table (allergies and special requests as pipe-separated tag lists).
            </p>
        </section>
    );
}
