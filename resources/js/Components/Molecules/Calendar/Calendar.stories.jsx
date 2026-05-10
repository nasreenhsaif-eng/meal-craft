import { useEffect, useState } from 'react';
import Calendar from './Calendar.jsx';

/**
 * @param {string} iso
 */
function formatLongDate(iso) {
    if (!iso) {
        return '—';
    }
    const parts = iso.split('-').map(Number);
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        return iso;
    }
    const [y, m, d] = parts;

    return new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric' }).format(
        new Date(y, m - 1, d),
    );
}

/** Realistic dashboard width — prevents Storybook from squeezing the calendar into a vertical strip */
function DashboardDemoChrome({ children }) {
    return (
        <div
            style={{ maxWidth: '400px', margin: '0 auto', width: '100%' }}
            className="box-border min-w-[320px]"
        >
            {children}
        </div>
    );
}

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Components/Calendar',
    component: Calendar,
    parameters: {
        layout: 'padded',
        docs: {
            description: {
                component:
                    'Meal-plan date picker: **single** or **range** selection, MealCraft **#6E8C47** selected states, 12px rounded focus (no default browser square ring). Overlays: `minimal-animated` seal for loading / success.',
            },
        },
    },
    argTypes: {
        mode: { control: 'select', options: ['single', 'range'] },
        identityState: { control: 'select', options: ['none', 'loading', 'success'] },
    },
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const SingleSelection = {
    render: function SingleRender() {
        const [v, setV] = useState('2026-05-12');
        return (
            <DashboardDemoChrome>
                <div className="space-y-3 font-sans">
                    <p className="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm font-medium leading-snug text-[#364153]">
                        <span className="shrink-0">Selected date</span>
                        <span className="whitespace-nowrap font-semibold text-[#6E8C47]" aria-live="polite">
                            {formatLongDate(v)}
                        </span>
                    </p>
                    <Calendar
                        mode="single"
                        value={v}
                        onChange={setV}
                        defaultMonth={new Date(2026, 4, 1)}
                        aria-label="Select a plan date"
                        className="w-full max-w-none"
                    />
                </div>
            </DashboardDemoChrome>
        );
    },
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const RangeSelection = {
    render: function RangeRender() {
        const [r, setR] = useState({ start: '2026-05-05', end: '2026-05-18' });
        return (
            <DashboardDemoChrome>
                <div className="space-y-3 font-sans">
                    <p className="text-sm font-medium leading-snug text-[#364153]">
                        <span className="mr-2 shrink-0 font-medium">Range</span>
                        <span className="inline whitespace-nowrap font-semibold text-[#6E8C47]">
                            {formatLongDate(r.start)} → {r.end ? formatLongDate(r.end) : '…'}
                        </span>
                    </p>
                    <Calendar
                        mode="range"
                        rangeValue={r}
                        onRangeChange={setR}
                        defaultMonth={new Date(2026, 4, 1)}
                        aria-label="Select meal plan date range"
                        className="w-full max-w-none"
                    />
                </div>
            </DashboardDemoChrome>
        );
    },
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const Identity = {
    render: function IdentityRender() {
        const [v, setV] = useState('2026-05-01');
        const [identityState, setIdentityState] = useState('loading');

        useEffect(() => {
            const t = window.setTimeout(() => setIdentityState('success'), 1200);
            const t2 = window.setTimeout(() => setIdentityState('none'), 3500);
            return () => {
                window.clearTimeout(t);
                window.clearTimeout(t2);
            };
        }, []);

        return (
            <DashboardDemoChrome>
                <Calendar
                    mode="single"
                    value={v}
                    defaultMonth={new Date(2026, 4, 1)}
                    identityState={identityState}
                    identityMessage={identityState === 'loading' ? 'Syncing your kitchen…' : identityState === 'success' ? 'Week confirmed' : ''}
                    onChange={setV}
                    className="w-full max-w-none"
                />
            </DashboardDemoChrome>
        );
    },
};
