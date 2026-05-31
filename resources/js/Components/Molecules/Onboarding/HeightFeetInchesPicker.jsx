import HeightSnapColumn from './HeightSnapColumn.jsx';
import { HEIGHT_FEET_OPTIONS, HEIGHT_INCHES } from './heightUtils.js';

/**
 * Dual adjacent ft/in scroll wheels for the onboarding height step.
 *
 * @param {{
 *   feet: number;
 *   inches: number;
 *   onFeetChange: (value: number) => void;
 *   onInchesChange: (value: number) => void;
 *   className?: string;
 * }} props
 */
export function HeightFeetInchesPicker({ feet, inches, onFeetChange, onInchesChange, className = '' }) {
    return (
        <div
            className={['flex w-full items-stretch gap-1 sm:gap-2', className].join(' ')}
            role="group"
            aria-label="Height in feet and inches"
        >
            <HeightSnapColumn
                ariaLabel="Feet"
                columnClassName="flex-1 basis-0"
                items={HEIGHT_FEET_OPTIONS}
                value={feet}
                onChange={(next) => onFeetChange(Number(next))}
                unitLabel="ft"
            />
            <HeightSnapColumn
                ariaLabel="Inches"
                columnClassName="flex-1 basis-0"
                items={HEIGHT_INCHES}
                value={inches}
                onChange={(next) => onInchesChange(Number(next))}
                unitLabel="in"
            />
        </div>
    );
}

export default HeightFeetInchesPicker;
