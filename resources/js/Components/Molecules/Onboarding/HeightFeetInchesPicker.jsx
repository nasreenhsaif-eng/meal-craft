import { WheelColumn } from './WheelColumn.jsx';
import { WheelPickerFrame } from './WheelPickerFrame.jsx';
import { HEIGHT_FEET_OPTIONS, HEIGHT_INCHES } from './heightUtils.js';

/**
 * Dual adjacent ft/in 3D scroll wheels for the onboarding height step.
 *
 * @param {{
 *   feet: number;
 *   inches: number;
 *   onFeetChange: (value: number) => void;
 *   onInchesChange: (value: number) => void;
 *   className?: string;
 *   visible?: boolean;
 * }} props
 */
export function HeightFeetInchesPicker({ feet, inches, onFeetChange, onInchesChange, className = '', visible = true }) {
    return (
        <WheelPickerFrame className={className}>
            <WheelColumn
                ariaLabel="Feet"
                columnClassName="flex-1 basis-0"
                items={HEIGHT_FEET_OPTIONS}
                value={feet}
                onChange={(next) => onFeetChange(Number(next))}
                unitLabel="ft"
                visible={visible}
            />
            <WheelColumn
                ariaLabel="Inches"
                columnClassName="flex-1 basis-0"
                items={HEIGHT_INCHES}
                value={inches}
                onChange={(next) => onInchesChange(Number(next))}
                unitLabel="in"
                visible={visible}
            />
        </WheelPickerFrame>
    );
}

export default HeightFeetInchesPicker;
