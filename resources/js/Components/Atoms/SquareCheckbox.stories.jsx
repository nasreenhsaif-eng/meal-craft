import { useState } from 'react';
import SquareCheckbox from './SquareCheckbox.jsx';

export default {
    title: 'MealCraft/Atoms/SquareCheckbox',
    component: SquareCheckbox,
    parameters: { layout: 'padded' },
};

export const Default = {
    name: 'Default (20×20)',
    render: function Render() {
        const [checked, setChecked] = useState(false);
        return (
            <div className="bg-white p-8">
                <SquareCheckbox checked={checked} onChange={() => setChecked((c) => !c)} />
            </div>
        );
    },
};

