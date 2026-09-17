import { useMemo, useState } from 'react';
import HeightFeetInchesPicker from './HeightFeetInchesPicker.jsx';
import MeasurementUnitToggle from './MeasurementUnitToggle.jsx';
import WheelNumberPicker from './WheelNumberPicker.jsx';
import {
    HEIGHT_CM_OPTIONS,
    clampFeetInches,
    clampHeightCm,
    cmToFeetInches,
    defaultHeightCm,
    feetInchesToCm,
} from './heightUtils.js';

function HeightPicker({ defaultUnit = 'cm', initialCm = defaultHeightCm() }) {
    const [unit, setUnit] = useState(defaultUnit);
    const [heightCm, setHeightCm] = useState(clampHeightCm(initialCm));

    const feetInches = useMemo(() => {
        const converted = cmToFeetInches(heightCm);

        return clampFeetInches(converted.feet, converted.inches);
    }, [heightCm]);

    return (
        <div className="flex w-full items-center justify-center gap-4 bg-white p-8 sm:gap-5">
            <div className={`min-w-0 shrink-0 ${unit === 'cm' ? 'w-[168px]' : 'w-[248px]'}`}>
                {unit === 'cm' ? (
                    <WheelNumberPicker
                        ariaLabel="Height in centimeters"
                        options={HEIGHT_CM_OPTIONS}
                        value={heightCm}
                        onChange={(next) => setHeightCm(clampHeightCm(Number(next)))}
                        unitLabel="cm"
                    />
                ) : (
                    <HeightFeetInchesPicker
                        feet={feetInches.feet}
                        inches={feetInches.inches}
                        onFeetChange={(nextFeet) => {
                            const clamped = clampFeetInches(nextFeet, feetInches.inches);
                            setHeightCm(feetInchesToCm(clamped.feet, clamped.inches));
                        }}
                        onInchesChange={(nextInches) => {
                            const clamped = clampFeetInches(feetInches.feet, nextInches);
                            setHeightCm(feetInchesToCm(clamped.feet, clamped.inches));
                        }}
                    />
                )}
            </div>
            <MeasurementUnitToggle className="shrink-0 self-center" value={unit} onChange={setUnit} />
        </div>
    );
}

export default {
    title: 'Design System/03. Molecules/Form and pickers/Height',
    component: HeightPicker,
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Default = {
    render: () => <HeightPicker defaultUnit="cm" initialCm={170} />,
};
