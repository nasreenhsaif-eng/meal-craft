import SquareCheckbox from '../../Atoms/Icons/SquareCheckbox.jsx';
import { formatShoppingAmount } from '../../../customer/customerShoppingList.js';

/**
 * @param {object} props
 * @param {{ key: string, name: string, amount: number, unit: string, ingredientId?: number, label?: string }} props.row
 * @param {boolean} props.checked
 * @param {(row: { key: string, name: string, amount: number, unit: string, ingredientId?: number }, shouldAdd: boolean) => void} props.onToggle
 * @param {string} props.checkboxId
 */
export function ShoppingIngredientCheckboxRow({ row, checked, onToggle, checkboxId }) {
    const amountLabel = row.label ? '' : formatShoppingAmount(row.amount, row.unit);

    return (
        <label
            htmlFor={checkboxId}
            className="group/item relative flex cursor-pointer items-center gap-3 rounded-[10px] px-1 py-2 hover:bg-[#F8F9F6]"
            onMouseDown={(event) => {
                // Focusing the hidden checkbox makes the phone frame scroll,
                // which lifts the footer and leaves a blank gap under it.
                event.preventDefault();
            }}
        >
            <input
                id={checkboxId}
                type="checkbox"
                className="sr-only"
                tabIndex={-1}
                checked={checked}
                onChange={() => onToggle(row, !checked)}
                onFocus={(event) => {
                    event.currentTarget.blur();
                }}
            />
            <SquareCheckbox checked={checked} presentational />
            <span className="min-w-0 flex-1 font-body text-sm text-[#262A22]">{row.label || row.name}</span>
            {amountLabel ? (
                <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-[#555555]">
                    {amountLabel}
                </span>
            ) : null}
        </label>
    );
}

/**
 * Ingredient rows with a checkbox that adds or removes the shopping-list item.
 *
 * @param {object} props
 * @param {Array<{ key: string, name: string, amount: number, unit: string, label?: string }>} props.rows
 * @param {Set<string>} props.checkedKeys
 * @param {(row: { key: string, name: string, amount: number, unit: string, ingredientId?: number }, shouldAdd: boolean) => void} props.onToggle
 * @param {string} [props.idPrefix]
 * @param {string} [props.className]
 */
export default function ShoppingIngredientChecklist({
    rows,
    checkedKeys,
    onToggle,
    idPrefix = 'shop',
    className = '',
}) {
    if (!rows?.length) {
        return null;
    }

    return (
        <ul className={['space-y-1', className].filter(Boolean).join(' ')} role="list">
            {rows.map((row) => (
                <li key={row.key}>
                    <ShoppingIngredientCheckboxRow
                        row={row}
                        checked={checkedKeys.has(row.key)}
                        onToggle={onToggle}
                        checkboxId={`${idPrefix}-${row.key}`}
                    />
                </li>
            ))}
        </ul>
    );
}
