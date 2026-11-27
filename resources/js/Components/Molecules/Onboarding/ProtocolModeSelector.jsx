import { useState } from 'react';
import OnboardingOptionButton from '../../Atoms/Button/OnboardingOptionButton.jsx';
import FastingSafetyNotice from './FastingSafetyNotice.jsx';
import OnboardingInlineDescription from './OnboardingInlineDescription.jsx';

/**
 * @param {{
 *   value?: 'non_fasting' | 'fasting' | string | null;
 *   onChange?: (value: 'non_fasting' | 'fasting') => void;
 *   disabled?: boolean;
 *   className?: string;
 * }} props
 */
export default function ProtocolModeSelector({
    value = 'non_fasting',
    onChange,
    disabled = false,
    className = '',
}) {
    const selected = value === 'fasting' ? 'fasting' : 'non_fasting';
    const [showFastingSafety, setShowFastingSafety] = useState(false);

    /** @type {Array<{ id: 'non_fasting' | 'fasting'; label: string; description: string }>} */
    const options = [
        {
            id: 'non_fasting',
            label: 'Meals only',
            description: 'Regular daily meals synced to your cycle.',
        },
        {
            id: 'fasting',
            label: 'Meals + fasting',
            description: 'Adds phase-adjusted fasting windows.',
        },
    ];

    /**
     * @param {'non_fasting' | 'fasting'} next
     */
    const handleSelect = (next) => {
        if (disabled) {
            return;
        }

        onChange?.(next);

        if (next === 'fasting') {
            setShowFastingSafety(true);
        }
    };

    return (
        <>
            <div
                className={`flex w-full flex-col gap-2.5 ${className}`.trim()}
                role="radiogroup"
                aria-label="Include intermittent fasting?"
            >
                {options.map((option) => {
                    const isActive = selected === option.id;
                    const descriptionId = `protocol-mode-desc-${option.id}`;

                    return (
                        <div key={option.id} className="flex w-full flex-col">
                            <OnboardingOptionButton
                                label={option.label}
                                selected={isActive}
                                disabled={disabled}
                                onSelect={() => handleSelect(option.id)}
                                describedBy={descriptionId}
                            />
                            <div
                                className={[
                                    'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                                    isActive ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                ].join(' ')}
                                aria-hidden={!isActive}
                            >
                                <div className="min-h-0 overflow-hidden">
                                    <OnboardingInlineDescription id={descriptionId}>
                                        {option.description}
                                    </OnboardingInlineDescription>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {showFastingSafety ? (
                <FastingSafetyNotice onDismiss={() => setShowFastingSafety(false)} />
            ) : null}
        </>
    );
}
