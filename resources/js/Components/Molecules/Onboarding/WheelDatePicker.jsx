import { WheelColumn } from './WheelColumn.jsx';
import { WheelPickerFrame } from './WheelPickerFrame.jsx';
import { MONTH_LABELS } from './wheelDateUtils.js';

/**
 * Scrollable month / day / year wheel picker for onboarding birthday capture.
 *
 * @param {{
 *   month: number;
 *   day: number;
 *   year: number;
 *   onChange: (value: { month: number; day: number; year: number }) => void;
 *   monthOptions?: number[];
 *   dayOptions?: number[];
 *   yearOptions?: number[];
 *   className?: string;
 *   visible?: boolean;
 * }} props
 */
export function WheelDatePicker({
    month,
    day,
    year,
    onChange,
    monthOptions = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
    dayOptions,
    yearOptions,
    className = '',
    visible = true,
}) {
    const resolvedDayOptions = dayOptions ?? Array.from({ length: 31 }, (_, index) => index + 1);

    const handleMonthChange = (nextMonth) => {
        const numericMonth = Number(nextMonth);
        onChange({
            month: numericMonth,
            day: Math.min(day, new Date(year, numericMonth, 0).getDate()),
            year,
        });
    };

    const handleDayChange = (nextDay) => {
        onChange({ month, day: Number(nextDay), year });
    };

    const handleYearChange = (nextYear) => {
        const numericYear = Number(nextYear);
        onChange({
            month,
            day: Math.min(day, new Date(numericYear, month, 0).getDate()),
            year: numericYear,
        });
    };

    return (
        <WheelPickerFrame className={className}>
            <WheelColumn
                ariaLabel="Birth month"
                columnClassName="flex-[1.45] basis-0"
                items={monthOptions}
                value={month}
                onChange={handleMonthChange}
                visible={visible}
                formatItem={(value) => MONTH_LABELS[Number(value) - 1] ?? String(value)}
            />
            <WheelColumn
                ariaLabel="Birth day"
                columnClassName="flex-[0.65] basis-0"
                items={resolvedDayOptions}
                value={day}
                onChange={handleDayChange}
                visible={visible}
            />
            <WheelColumn
                ariaLabel="Birth year"
                columnClassName="flex-[0.9] basis-0"
                items={yearOptions ?? []}
                value={year}
                onChange={handleYearChange}
                visible={visible}
            />
        </WheelPickerFrame>
    );
}

export default WheelDatePicker;
