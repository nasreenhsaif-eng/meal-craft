import { useState } from 'react';
import MealCardClientView from './MealCardClientView.jsx';

export default {
    title: 'MealCraft/Components/MealCardClientView',
    component: MealCardClientView,
    parameters: {
        layout: 'padded',
    },
};

export const Default = {
    render: () => {
        const [selected, setSelected] = useState(false);
        return (
            <div className="max-w-sm">
                <MealCardClientView
                    title="Post-workout salmon bowl"
                    imageUrl="https://images.unsplash.com/photo-1553621042-f6e147245754?auto=format&fit=crop&w=1400&q=80"
                    macros={{ calories: 520, protein: '44g', carbs: '38g', fat: '22g' }}
                    selected={selected}
                    onToggleSelected={() => setSelected((v) => !v)}
                    onViewDetails={() => {}}
                />
            </div>
        );
    },
};

export const StackedDeckSizes = {
    name: 'Sizes (mobile vs desktop)',
    render: () => (
        <div className="flex flex-wrap items-start gap-6">
            <div className="w-[320px]">
                <p className="mb-2 text-xs font-semibold text-neutral-600">Mobile width</p>
                <div className="w-[320px] overflow-hidden rounded-xl border border-neutral-200 bg-[#F8F9F6] p-4">
                    <MealCardClientView
                        title="Egg white veggie scramble"
                        imageUrl="https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=1400&q=80"
                        macros={{ calories: 312, protein: '28g', carbs: '12g', fat: '16g' }}
                    />
                </div>
            </div>
            <div className="w-[720px]">
                <p className="mb-2 text-xs font-semibold text-neutral-600">Desktop width</p>
                <div className="w-[720px] overflow-hidden rounded-xl border border-neutral-200 bg-[#F8F9F6] p-4">
                    <MealCardClientView
                        title="Herb chicken quinoa plate"
                        imageUrl="https://images.unsplash.com/photo-1604908177225-6b9dd98ec605?auto=format&fit=crop&w=1400&q=80"
                        macros={{ calories: 610, protein: '48g', carbs: '58g', fat: '19g' }}
                        selected
                    />
                </div>
            </div>
        </div>
    ),
};

