import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

/**
 * Thin wrapper so stories / legacy imports still resolve to the old portrait craft card.
 *
 * Protocol shells (`ProtocolMealSlotCard`, options screen, fixed sides) render
 * {@link MealCardClientViewNano} directly.
 *
 * @param {object} props
 * @param {{ id?: string; title?: string; imageUrl?: string; macros?: { calories?: string|number; protein?: string|number; carbs?: string|number; fat?: string|number } }} props.meal
 * @param {boolean} [props.selected]
 * @param {boolean} [props.showCheckbox] Ignored — CRAFT THIS MEAL / SELECTED is the toggle.
 * @param {boolean} [props.compact] Ignored — Nano deck layout is used.
 * @param {() => void} [props.onSelect]
 * @param {() => void} [props.onViewDetails]
 * @param {() => void} [props.onEdit]
 * @param {string} [props.className]
 * @param {boolean} [props.hideCraftButton]
 */
export default function ProtocolMealRow({
    meal,
    selected = false,
    showCheckbox = false,
    compact = false,
    onSelect,
    onViewDetails,
    onEdit,
    className = '',
    hideCraftButton = false,
}) {
    void showCheckbox;
    void compact;

    const title = String(meal?.title ?? '').trim() || 'Meal';
    const imageUrl = typeof meal?.imageUrl === 'string' ? meal.imageUrl : undefined;
    const interactive = typeof onSelect === 'function';

    return (
        <MealCardClientViewNano
            deck
            alignActionsBottom
            className={className}
            title={title}
            imageUrl={imageUrl}
            macros={meal?.macros}
            selected={selected}
            hideCraftButton={hideCraftButton || !interactive}
            onToggleSelected={interactive ? onSelect : undefined}
            onViewDetails={onViewDetails}
            onEdit={onEdit}
        />
    );
}
