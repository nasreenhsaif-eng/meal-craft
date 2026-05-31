import { useState } from 'react';
import { WheelDatePicker } from './WheelDatePicker.jsx';
import { buildDayOptions, buildYearOptions, defaultBirthdayValue, toIsoDate } from './wheelDateUtils.js';

export default {
    title: 'MealCraft/Molecules/Onboarding/WheelDatePicker',
    component: WheelDatePicker,
    parameters: {
        docs: {
            description: {
                component: 'Scrollable month / day / year wheel used on the onboarding birthday step.',
            },
        },
    },
};

export const Default = {
    render: function Render() {
        const initial = defaultBirthdayValue();
        const [parts, setParts] = useState(initial);
        const yearOptions = buildYearOptions();
        const dayOptions = buildDayOptions(parts.month, parts.year);

        return (
            <div className="mx-auto w-full max-w-2xl p-4">
                <WheelDatePicker
                    className="w-full"
                    month={parts.month}
                    day={parts.day}
                    year={parts.year}
                    dayOptions={dayOptions}
                    yearOptions={yearOptions}
                    onChange={setParts}
                />
                <p className="mt-4 text-center font-montserrat text-sm text-[#555555]">
                    Selected: {toIsoDate(parts)}
                </p>
            </div>
        );
    },
};
