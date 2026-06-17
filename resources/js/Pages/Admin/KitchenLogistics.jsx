import { useCallback, useEffect, useState } from 'react';
import KitchenDailySheet from '../../Components/Logistics/KitchenDailySheet.jsx';
import KitchenShoppingList from '../../Components/Logistics/KitchenShoppingList.jsx';
import { downloadCsv, buildKitchenDailySheetCsv } from '../../Components/Logistics/logisticsCsvExport.js';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import { fetchKitchenDailySheet } from '../../logistics/fetchKitchenDailySheet.js';

/**
 * @param {{ initialProductionDate: string; kitchenDailySheetUrl?: string }} props
 */
function KitchenLogisticsView({ initialProductionDate, kitchenDailySheetUrl = '/api/admin/kitchen/daily-sheet' }) {
    const [productionDate, setProductionDate] = useState(initialProductionDate);
    const [rows, setRows] = useState(/** @type {import('../../Components/Logistics/logisticsMockData.js').KitchenDailyRow[]} */ ([]));
    const [ingredientLines, setIngredientLines] = useState(
        /** @type {import('../../Components/Logistics/logisticsMockData.js').IngredientChoiceLine[]} */ ([]),
    );
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(/** @type {string | null} */ (null));

    const loadSheet = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const payload = await fetchKitchenDailySheet(productionDate, kitchenDailySheetUrl);
            setRows(payload.rows);
            setIngredientLines(payload.ingredientLines);
        } catch (loadError) {
            setRows([]);
            setIngredientLines([]);
            setError(loadError instanceof Error ? loadError.message : 'Could not load kitchen sheet.');
        } finally {
            setLoading(false);
        }
    }, [kitchenDailySheetUrl, productionDate]);

    useEffect(() => {
        loadSheet();
    }, [loadSheet]);

    const handleGenerateCsv = useCallback(() => {
        const csv = buildKitchenDailySheetCsv(rows, productionDate);
        downloadCsv(`kitchen-daily-${productionDate || 'undated'}.csv`, csv);
    }, [productionDate, rows]);

    return (
        <div className="space-y-8">
            <div>
                <h1 className="font-montserrat text-2xl font-semibold text-[#262A22]">Kitchen production</h1>
                <p className="mt-2 text-sm text-[#555555]">
                    Customer craft plans for the selected production date, with scaled ingredient lines for mains and
                    breakfast.
                </p>
            </div>

            {loading ? (
                <p className="rounded-[12px] border border-gray-200 bg-white px-4 py-3 text-sm text-[#555555]">
                    Loading kitchen sheet…
                </p>
            ) : null}

            {error ? (
                <p className="rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{error}</p>
            ) : null}

            <KitchenDailySheet
                rows={rows}
                productionDate={productionDate}
                onProductionDateChange={setProductionDate}
                onGenerateCsvForDrive={handleGenerateCsv}
            />

            <KitchenShoppingList
                lines={ingredientLines}
                dayLabel={productionDate}
                onGenerateCsvForDrive={() =>
                    downloadCsv(
                        `kitchen-ingredients-${productionDate || 'undated'}.csv`,
                        'Customer,Meal,Ingredient,Grams\n' +
                            ingredientLines
                                .map(
                                    (line) =>
                                        `"${line.recipeLabel?.split(' — ')[0] ?? ''}","${line.recipeLabel?.split(' — ')[1] ?? ''}","${line.ingredient}",${line.gramsPerPortion}`,
                                )
                                .join('\n'),
                    )
                }
            />
        </div>
    );
}

export default function KitchenLogistics(props) {
    return <KitchenLogisticsView {...props} />;
}

KitchenLogistics.layout = adminInertiaLayout;
