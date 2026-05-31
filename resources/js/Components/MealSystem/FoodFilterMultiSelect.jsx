import TextInput from '../Atoms/TextInput/TextInput.jsx';
import FoodFilterPill from './FoodFilterPill.jsx';
import { FOOD_FILTER_OPTIONS, FOOD_FILTER_OTHER_ID } from './foodFilterOptions.js';

/**
 * @typedef {import('./foodFilterOptions.js').FoodFilterId} FoodFilterId
 */

/**
 * @param {FoodFilterId[]} selected
 * @param {FoodFilterId} id
 */
function toggleFilterSelection(selected, id) {
    if (selected.includes(id)) {
        return selected.filter((entry) => entry !== id);
    }

    return [...selected, id];
}

/**
 * Multi-select food filters with icon-led pills and dynamic Other input.
 *
 * @param {{
 *   value?: FoodFilterId[];
 *   onChange?: (value: FoodFilterId[]) => void;
 *   otherText?: string;
 *   onOtherTextChange?: (value: string) => void;
 *   className?: string;
 * }} props
 */
export function FoodFilterMultiSelect({
    value = [],
    onChange,
    otherText = '',
    onOtherTextChange,
    className = '',
}) {
    const isOtherActive = value.includes(FOOD_FILTER_OTHER_ID);

    const handleToggle = (id) => {
        const nextSelected = toggleFilterSelection(value, id);

        onChange?.(nextSelected);

        if (id === FOOD_FILTER_OTHER_ID && !nextSelected.includes(FOOD_FILTER_OTHER_ID)) {
            onOtherTextChange?.('');
        }
    };

    return (
        <div className={`w-full min-w-0 ${className}`.trim()}>
            <div
                className="flex flex-wrap justify-center gap-3"
                role="group"
                aria-label="Food filter options"
            >
                {FOOD_FILTER_OPTIONS.map((option) => (
                    <FoodFilterPill
                        key={option.id}
                        label={option.label}
                        icon={<option.Icon />}
                        isActive={value.includes(option.id)}
                        onClick={() => handleToggle(option.id)}
                    />
                ))}
            </div>

            {isOtherActive ? (
                <div className="mt-4 w-full">
                    <TextInput
                        label="Other ingredients or sensitivities"
                        value={otherText}
                        onChange={(event) => onOtherTextChange?.(event.target.value)}
                        placeholder="Please specify other ingredients or sensitivities..."
                    />
                </div>
            ) : null}
        </div>
    );
}

export default FoodFilterMultiSelect;
