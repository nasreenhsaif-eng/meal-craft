import { WheelColumn } from './WheelColumn.jsx';
import { WheelPickerFrame } from './WheelPickerFrame.jsx';

/**
 * Single-column 3D scroll wheel for numeric onboarding values (height, weight, etc.).
 *
 * @param {{
 *   value: number;
 *   options: number[];
 *   onChange: (value: number) => void;
 *   ariaLabel: string;
 *   unitLabel?: string;
 *   formatItem?: (value: number) => string;
 *   className?: string;
 *   columnClassName?: string;
 *   visible?: boolean;
 * }} props
 */
export function WheelNumberPicker({
    value,
    options,
    onChange,
    ariaLabel,
    unitLabel,
    formatItem,
    className = '',
    columnClassName = '',
    visible = true,
}) {
    return (
        <WheelPickerFrame className={className}>
            <WheelColumn
                ariaLabel={ariaLabel}
                items={options}
                value={value}
                onChange={(next) => onChange(Number(next))}
                unitLabel={unitLabel}
                columnClassName={columnClassName}
                visible={visible}
                formatItem={formatItem ? (item) => formatItem(Number(item)) : undefined}
            />
        </WheelPickerFrame>
    );
}

export default WheelNumberPicker;
