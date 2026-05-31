import { useState } from 'react';
import { HeightFeetInchesPicker } from './HeightFeetInchesPicker.jsx';
import { clampFeetInches, cmToFeetInches, defaultHeightCm } from './heightUtils.js';

export default {
    title: 'MealCraft/Molecules/Onboarding/HeightFeetInchesPicker',
    component: HeightFeetInchesPicker,
    parameters: {
        layout: 'centered',
    },
};

export const Default = {
    render: () => {
        const initial = cmToFeetInches(defaultHeightCm());
        const clamped = clampFeetInches(initial.feet, initial.inches);
        const [feet, setFeet] = useState(clamped.feet);
        const [inches, setInches] = useState(clamped.inches);

        return (
            <div className="w-[248px]">
                <HeightFeetInchesPicker
                    feet={feet}
                    inches={inches}
                    onFeetChange={setFeet}
                    onInchesChange={setInches}
                />
            </div>
        );
    },
};
