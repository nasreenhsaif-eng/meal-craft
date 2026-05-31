import { WheelColumn } from './WheelColumn.jsx';
import { WheelPickerFrame } from './WheelPickerFrame.jsx';

/**
 * Single-column scroll wheel for numeric onboarding values (height, weight, etc.).
 *
 * @param {{
 *   value: number;
 *   options: number[];
 *   onChange: (value: number) => void;
 *   ariaLabel: string;
 *   formatItem?: (value: number) => string;
 *   className?: string;
 * }} props
 */
export function WheelNumberPicker({ value, options, onChange, ariaLabel, formatItem, className = '' }) {
    return (
        <WheelPickerFrame className={className}>
            <WheelColumn
                ariaLabel={ariaLabel}
                items={options}
                value={value}
                onChange={(next) => onChange(Number(next))}
                formatItem={formatItem ? (item) => formatItem(Number(item)) : undefined}
            />
        </WheelPickerFrame>
    );
}

export default WheelNumberPicker;
