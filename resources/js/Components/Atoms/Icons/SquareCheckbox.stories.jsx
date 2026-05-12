import { useState } from 'react';
import SquareCheckbox from './SquareCheckbox.jsx';

export default {
    title: 'MealCraft/Atoms/Buttons & Links/Icons/SquareCheckbox',
    component: SquareCheckbox,
    parameters: { layout: 'padded' },
};

/** Interactive selection — use in forms or when the div handles its own click. */
export const SelectionInteractive = {
    name: 'Selection (interactive)',
    render: function Render() {
        const [checked, setChecked] = useState(false);
        return (
            <div className="bg-white p-8">
                <SquareCheckbox checked={checked} onChange={() => setChecked((c) => !c)} />
            </div>
        );
    },
};

/** Presentational — pair with a native checkbox or parent `button` (e.g. table row / “select all”). */
export const SelectionPresentational = {
    name: 'Selection (presentational)',
    render: function Render() {
        const [checked, setChecked] = useState(true);
        return (
            <div className="bg-white p-8">
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-md font-body text-sm text-[#1F2937] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                    onClick={() => setChecked((c) => !c)}
                    aria-pressed={checked}
                >
                    <SquareCheckbox checked={checked} presentational />
                    Toggle row
                </button>
            </div>
        );
    },
};

export { SelectionInteractive as Default };
