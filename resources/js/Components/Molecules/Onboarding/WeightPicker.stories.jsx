import { useMemo, useState } from 'react';
import WheelNumberPicker from './WheelNumberPicker.jsx';
import WeightUnitToggle from './WeightUnitToggle.jsx';
import {
    WEIGHT_KG_OPTIONS,
    WEIGHT_LB_OPTIONS,
    clampWeightKg,
    defaultWeightKg,
    kgToLb,
    lbToKg,
} from './weightUtils.js';

function WeightPicker({ defaultUnit = 'kg', initialKg = defaultWeightKg() }) {
    const [unit, setUnit] = useState(defaultUnit);
    const [weightKg, setWeightKg] = useState(clampWeightKg(initialKg));

    const wheelOptions = unit === 'kg' ? WEIGHT_KG_OPTIONS : WEIGHT_LB_OPTIONS;
    const wheelValue = useMemo(
        () => (unit === 'kg' ? clampWeightKg(weightKg) : kgToLb(weightKg)),
        [unit, weightKg],
    );

    return (
        <div className="flex w-full items-center justify-center gap-4 bg-white p-8 sm:gap-5">
            <div className="w-[168px] min-w-0 shrink-0">
                <WheelNumberPicker
                    ariaLabel={unit === 'kg' ? 'Weight in kilograms' : 'Weight in pounds'}
                    options={wheelOptions}
                    value={wheelValue}
                    onChange={(nextValue) => {
                        if (unit === 'kg') {
                            setWeightKg(clampWeightKg(Number(nextValue)));
                            return;
                        }

                        setWeightKg(lbToKg(Number(nextValue)));
                    }}
                    unitLabel={unit}
                />
            </div>
            <WeightUnitToggle className="shrink-0 self-center" value={unit} onChange={setUnit} />
        </div>
    );
}

export default {
    title: 'Design System/03. Molecules/Form and pickers/Weight',
    component: WeightPicker,
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Kilograms = {
    name: 'Kilograms',
    render: () => <WeightPicker defaultUnit="kg" initialKg={68} />,
};

export const Pounds = {
    name: 'Pounds',
    render: () => <WeightPicker defaultUnit="lb" initialKg={72} />,
};
