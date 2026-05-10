/**
 * Logistics operational sheets. For the **meal-plan date picker** UI, use
 * `MealCraft/Components/Calendar` — single + range selection with MealCraft green chrome.
 */
import { useState } from 'react';
import { AdminLayout } from '../Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../Admin/AdminSidebar.jsx';
import UserSubmissions from './UserSubmissions.jsx';
import KitchenDailySheet from './KitchenDailySheet.jsx';
import KitchenShoppingList from './KitchenShoppingList.jsx';
import { downloadCsv } from './logisticsCsvExport.js';
import { mockUserSubmissions, mockKitchenDailyRows, mockIngredientChoiceLines } from './logisticsMockData.js';

/** Simulates US Letter-ish sheet on screen; `@media print` relies on browser print. */
function printPaperDecorator(Story) {
    return (
        <div className="min-h-screen bg-[#D1D5DB] p-4 md:p-8 print:bg-white print:p-0">
            <div className="mx-auto max-w-[8.5in] rounded-sm border border-neutral-400 bg-white shadow-2xl print:max-w-none print:border-0 print:shadow-none print:rounded-none">
                <div className="p-6 print:p-4">
                    <Story />
                </div>
            </div>
            <p className="mx-auto mt-4 max-w-[8.5in] text-center text-xs font-medium text-[#555555] print:hidden">
                Print preview — use the browser print dialog to match paper output.
            </p>
        </div>
    );
}

/** Demo: persist CSV locally; replace with Drive API / Laravel in production. */
function logisticsCsvDownload(filenameBase) {
    return (/** @type {string} */ csv) => {
        downloadCsv(`${filenameBase}.csv`, csv);
    };
}

export default {
    title: 'MealCraft/Pages/Admin/Logistics',
    parameters: {
        layout: 'fullscreen',
    },
};

export const UserSubmissionsAdmin = {
    name: 'User submissions (admin shell)',
    render: function Render() {
        const [date, setDate] = useState('2026-04-12');
        return (
            <AdminLayout pageTitle="User submissions" activePath={ADMIN_NAV_PATHS.customerProfiles} searchLabel="Search submissions">
                <UserSubmissions
                    submissions={mockUserSubmissions}
                    selectedDate={date}
                    onDateChange={setDate}
                    onPrint={() => window.print()}
                    onSendToGoogleDrive={logisticsCsvDownload(`user-submissions-drive-${date}`)}
                    onExportCsv={logisticsCsvDownload(`user-submissions-export-${date}`)}
                />
            </AdminLayout>
        );
    },
};

export const UserSubmissionsTable = {
    name: 'User submissions (table only)',
    parameters: { layout: 'padded' },
    render: function Render() {
        const [date, setDate] = useState('');
        return (
            <div className="mx-auto max-w-6xl bg-[#F9FAFB] p-6">
                <UserSubmissions
                    submissions={mockUserSubmissions}
                    selectedDate={date}
                    onDateChange={setDate}
                    onPrint={() => window.print()}
                    onSendToGoogleDrive={logisticsCsvDownload('user-submissions-drive-all')}
                    onExportCsv={logisticsCsvDownload('user-submissions-export-all')}
                />
            </div>
        );
    },
};

export const KitchenProductionDaily = {
    name: 'Kitchen production — daily sheet',
    parameters: { layout: 'padded' },
    render: function Render() {
        const [d, setD] = useState('2026-04-14');
        return (
            <div className="mx-auto max-w-6xl bg-[#F9FAFB] p-6">
                <KitchenDailySheet
                    rows={mockKitchenDailyRows}
                    productionDate={d}
                    onProductionDateChange={setD}
                    onGenerateCsvForDrive={logisticsCsvDownload(`kitchen-daily-${d}`)}
                    printPreview={false}
                />
            </div>
        );
    },
};

export const KitchenProductionPrintPreview = {
    name: 'Kitchen production — print preview',
    decorators: [printPaperDecorator],
    render: () => (
        <KitchenDailySheet
            rows={mockKitchenDailyRows}
            productionDate="2026-04-14"
            onProductionDateChange={() => {}}
            onGenerateCsvForDrive={logisticsCsvDownload('kitchen-daily-print-preview')}
            printPreview
            title="Kitchen daily sheet — print preview"
        />
    ),
};

export const KitchenShoppingAggregated = {
    name: 'Kitchen shopping — aggregated list',
    parameters: { layout: 'padded' },
    render: () => (
        <div className="mx-auto max-w-6xl bg-[#F9FAFB] p-6">
            <KitchenShoppingList
                lines={mockIngredientChoiceLines}
                dayLabel="Monday, Apr 14, 2026"
                onGenerateCsvForDrive={() =>
                    downloadCsv('kitchen-shopping-demo.csv', 'Section,Row,Value\nBulk,Salmon (g),1950')
                }
            />
        </div>
    ),
};

export const KitchenLogisticsInAdminLayout = {
    name: 'Kitchen logistics full flow (admin shell)',
    render: function Render() {
        const [d, setD] = useState('2026-04-14');
        return (
            <AdminLayout pageTitle="Kitchen production" activePath={ADMIN_NAV_PATHS.mealHub} searchLabel="Search kitchen">
                <div className="space-y-10">
                    <KitchenDailySheet
                        rows={mockKitchenDailyRows}
                        productionDate={d}
                        onProductionDateChange={setD}
                        onGenerateCsvForDrive={logisticsCsvDownload(`kitchen-daily-${d}`)}
                    />
                    <KitchenShoppingList
                        lines={mockIngredientChoiceLines}
                        dayLabel={d}
                        onGenerateCsvForDrive={() =>
                            downloadCsv(`kitchen-shopping-${d}.csv`, 'Section,Row,Value\nBulk,Salmon (g),1950')
                        }
                    />
                </div>
            </AdminLayout>
        );
    },
};
