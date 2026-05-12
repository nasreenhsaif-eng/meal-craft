import { useState } from 'react';
import RoundIconButton from './RoundIconButton.jsx';
import { IconDelete, IconEdit, IconLayoutGrid, IconLayoutList } from '../SvgIcons.jsx';

export default {
    title: 'MealCraft/Atoms/Buttons & Links/Icons/RoundIconButton',
    component: RoundIconButton,
    parameters: { layout: 'padded' },
    argTypes: {
        disabled: { control: 'boolean' },
        intent: { control: 'select', options: ['default', 'danger'] },
        ariaLabel: { control: 'text' },
    },
};

/** Admin card actions — icon-only; name comes from `ariaLabel`. */
export const AdminActions = {
    name: 'Admin actions',
    render: () => (
        <div className="flex items-center gap-3 bg-[#F9FAFB] p-6">
            <RoundIconButton icon={<IconEdit />} ariaLabel="Edit meal" intent="default" />
            <RoundIconButton icon={<IconDelete />} ariaLabel="Delete meal" intent="danger" />
        </div>
    ),
};

export const Default = AdminActions;

/** Same markup/classes as Meal Library view toggle (segmented group). */
export const ViewModeToggle = {
    name: 'View mode toggle (grid / list)',
    render: function Render() {
        const [mode, setMode] = useState('grid');
        const segmentActive = '!border-transparent bg-white text-[#262A22] shadow-sm';
        const segmentInactive = '!border-transparent bg-transparent text-[#6B7280] shadow-none hover:bg-white/70';
        return (
            <div className="flex flex-col gap-3 bg-[#F9FAFB] p-6">
                <p className="m-0 font-body text-sm text-[#555555]">Segmented control — matches Meal Library (rounded square, not circle).</p>
                <div
                    className="inline-flex w-max items-center gap-1 rounded-[12px] border border-[#E5E7EB] bg-[#F8F9F6] p-1"
                    role="group"
                    aria-label="Library view"
                >
                    <RoundIconButton
                        type="button"
                        icon={<IconLayoutGrid className={mode === 'grid' ? 'text-[#5A6B44]' : ''} />}
                        ariaLabel="Grid view"
                        aria-pressed={mode === 'grid'}
                        onClick={() => setMode('grid')}
                        className={mode === 'grid' ? segmentActive : segmentInactive}
                    />
                    <RoundIconButton
                        type="button"
                        icon={<IconLayoutList className={mode === 'list' ? 'text-[#5A6B44]' : ''} />}
                        ariaLabel="List view"
                        aria-pressed={mode === 'list'}
                        onClick={() => setMode('list')}
                        className={mode === 'list' ? segmentActive : segmentInactive}
                    />
                </div>
                <p className="m-0 font-mono text-xs text-[#6B7280]">Active: {mode}</p>
            </div>
        );
    },
};
