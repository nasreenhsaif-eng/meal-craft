import { useState } from 'react';
import Button from '../../../Components/Atoms/Button.jsx';
import CalendarRangeField from '../../../Components/Molecules/Calendar/CalendarRangeField.jsx';

const PAGE_BG = 'bg-[#F8F9F6]';
const WEEKDAYS = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
const TIERS = ['1250 KCAL', '1500 KCAL', '1800 KCAL', '2000 KCAL'];

const DEFAULT_RANGE = { start: '2026-09-27', end: '2026-10-03' };

/**
 * Storybook-only layout for the admin protocol editor.
 * Title and description on the left; one-line week calendar dropdown on the right.
 *
 * @param {{
 *   title?: string;
 *   description?: string;
 * }} props
 */
export default function ProtocolEditorMock({
    title = 'Balanced Anti-inflammatory',
    description = '',
}) {
    const [range, setRange] = useState(DEFAULT_RANGE);
    const [draft, setDraft] = useState(description);
    const [activeDay, setActiveDay] = useState(0);
    const [activeTier, setActiveTier] = useState(0);

    return (
        <div className={`min-h-full font-body ${PAGE_BG}`}>
            <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <p className="font-montserrat text-sm font-semibold text-[#5A6B44]">← Back to Meal Plan Library</p>

                <div className="mt-4 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(240px,320px)]">
                    <div className="min-w-0">
                        <h1 className="font-montserrat text-2xl font-bold tracking-tight text-[#262A22]">{title}</h1>
                        <label className="mt-3 block">
                            <span className="sr-only">Meal plan description</span>
                            <textarea
                                value={draft}
                                onChange={(event) => setDraft(event.target.value)}
                                rows={4}
                                placeholder="Nutrient-dense meal plan with balanced macronutrients and anti-inflammatory whole foods."
                                className="w-full resize-none rounded-[12px] border border-gray-200 bg-white px-3 py-2 font-body text-sm leading-relaxed text-[#262A22] placeholder:text-[#777777] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                            />
                        </label>
                        <p className="mt-3 max-w-3xl font-body text-sm text-[#555555]">
                            Select the default meals for each day, then publish. Customers start with these picks and
                            can still change them via SEE OTHER OPTIONS.
                        </p>
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            label="Publish meal plan"
                            className="mt-4"
                        />
                    </div>

                    <div className="min-w-0 lg:justify-self-end lg:w-full">
                        <CalendarRangeField
                            label="Plan week"
                            rangeValue={range}
                            onRangeChange={setRange}
                            placeholder="Select week"
                            className="w-full max-w-[320px] lg:ml-auto"
                        />
                    </div>
                </div>

                <div className="mt-6 rounded-[12px] border border-gray-200 bg-white p-3 sm:p-4">
                    <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Meal plan days">
                        {WEEKDAYS.map((day, index) => (
                            <Button
                                key={day}
                                type="button"
                                role="tab"
                                aria-selected={index === activeDay}
                                label={day}
                                variant={index === activeDay ? 'primary' : 'tab'}
                                size="sm"
                                onClick={() => setActiveDay(index)}
                            />
                        ))}
                    </div>
                </div>

                <div className="mt-4 rounded-[12px] border border-[#D7E0CC] bg-[#F3F6EF] p-4">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                        Preview calorie tier
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2" role="tablist" aria-label="Calorie tiers">
                        {TIERS.map((tier, index) => (
                            <Button
                                key={tier}
                                type="button"
                                role="tab"
                                aria-selected={index === activeTier}
                                label={tier}
                                variant={index === activeTier ? 'primary' : 'tab'}
                                size="sm"
                                onClick={() => setActiveTier(index)}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
