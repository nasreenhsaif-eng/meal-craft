import PillButton from '../Atoms/Button/Button.jsx';
import ProtocolMealRow from './ProtocolMealRow.jsx';

/** Same footprint as one Main Meals / overview card. */
const MEAL_CARD_SHELL =
    'overflow-hidden rounded-[10px] border border-gray-200 bg-[#F8F9F6]';

const MEAL_CARD_WIDTH =
    'w-full min-w-0 max-w-full md:w-[calc(50%-0.375rem)] md:max-w-[calc(50%-0.375rem)]';

/**
 * Full-screen “Choose Your Meals of the Day” list for SEE OTHER OPTIONS.
 *
 * Cards match the day-overview ProtocolMealRow chrome (soft fill, bordered shell).
 *
 * @param {object} props
 * @param {string} props.dayLabel e.g. "SUNDAY"
 * @param {string} props.sectionTitle e.g. "Main Meals"
 * @param {Array<object>} props.options
 * @param {string[]} props.selectedIds
 * @param {number} props.maxSelected
 * @param {(meal: object) => void} props.onToggle
 * @param {(meal: object) => void} [props.onViewDetails]
 * @param {() => void} props.onBack
 * @param {() => void} [props.onConfirm] Defaults to onBack when omitted.
 * @param {string} [props.className]
 */
export default function ProtocolMealOptionsScreen({
    dayLabel = '',
    sectionTitle = 'Meals',
    options = [],
    selectedIds = [],
    maxSelected = 1,
    onToggle,
    onViewDetails,
    onBack,
    onConfirm,
    className = '',
}) {
    const selectedSet = new Set((selectedIds ?? []).map((id) => String(id)));
    const optionCount = options.length;
    const selectedCount = selectedIds.length;
    const handleConfirm = typeof onConfirm === 'function' ? onConfirm : onBack;
    const centerPair = optionCount > 0 && optionCount <= 2;

    return (
        <div
            className={[
                'flex h-full min-h-0 flex-col bg-[#F8F9F6]',
                className,
            ]
                .join(' ')
                .trim()}
        >
            <div className="shrink-0 border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
                <p className="font-montserrat text-[13px] font-bold uppercase tracking-wide text-[#262A22]">
                    CRAFTING YOUR {String(dayLabel).toUpperCase()}
                </p>
                <h2 className="mt-1 font-montserrat text-xl font-bold tracking-tight text-[#262A22] sm:text-2xl">
                    Choose Your Meals of the Day.
                </h2>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                <section className="overflow-hidden rounded-[12px] border border-[#5A6B44] bg-white">
                    <div className="px-4 pb-3 pt-4">
                        <h3 className="font-montserrat text-base font-bold text-[#262A22]">{sectionTitle}</h3>
                        <p className="mt-0.5 font-body text-sm italic text-[#555555]">
                            {optionCount} options · {selectedCount}/{maxSelected} selected
                        </p>
                        <div className="mt-3 border-t border-dashed border-[#8F55A8]" aria-hidden />
                    </div>

                    <ul
                        className={[
                            'm-0 flex list-none flex-wrap gap-3 px-3 pb-4',
                            centerPair ? 'justify-center' : 'justify-start',
                        ].join(' ')}
                    >
                        {options.map((meal, index) => {
                            const id = String(meal?.id ?? index);
                            const selected = selectedSet.has(id);

                            return (
                                <li
                                    key={id}
                                    className={[
                                        MEAL_CARD_WIDTH,
                                        MEAL_CARD_SHELL,
                                        selected ? 'ring-2 ring-inset ring-[#5A6B44]/35' : '',
                                    ]
                                        .join(' ')
                                        .trim()}
                                >
                                    <ProtocolMealRow
                                        meal={meal}
                                        selected={selected}
                                        showCheckbox
                                        compact
                                        onSelect={() => onToggle?.(meal)}
                                        onViewDetails={
                                            typeof onViewDetails === 'function'
                                                ? () => onViewDetails(meal)
                                                : undefined
                                        }
                                    />
                                </li>
                            );
                        })}
                    </ul>
                </section>
            </div>

            <div className="shrink-0 border-t border-gray-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div className="flex items-center justify-between gap-3">
                    <PillButton
                        type="button"
                        label="BACK"
                        variant="outline"
                        onClick={onBack}
                        className="shrink-0"
                    />
                    <PillButton
                        type="button"
                        label="CONFIRM"
                        variant="primary"
                        onClick={handleConfirm}
                        className="shrink-0"
                    />
                </div>
            </div>
        </div>
    );
}
