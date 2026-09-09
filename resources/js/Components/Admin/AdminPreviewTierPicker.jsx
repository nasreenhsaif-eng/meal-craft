import PillButton from '../Atoms/Button/Button.jsx';

/**
 * Admin-only calorie tier pills for previewing scaled breakfast and mains.
 *
 * @param {object} props
 * @param {number[]} props.tiers
 * @param {number} props.selectedTier
 * @param {(tier: number) => void} props.onSelectTier
 * @param {boolean} [props.compact]
 * @param {boolean} [props.loading]
 * @param {string} [props.description]
 * @param {string} [props.compactHint]
 */
export default function AdminPreviewTierPicker({
    tiers,
    selectedTier,
    onSelectTier,
    compact = false,
    loading = false,
    description = 'Pick a daily total to load the matching Meal Tiers Library portions. Meal cards show one calorie.',
    compactHint = 'Pick a daily total to load the matching Meal Tiers Library portions. Meal cards show one calorie. Side salads, desserts, and soup stay at their authored kitchen portions.',
}) {
    return (
        <div
            className={
                compact
                    ? 'mt-3 shrink-0 border-t border-[#5A6B44]/15 pt-3'
                    : 'rounded-[12px] border border-[#5A6B44]/25 bg-[#5A6B44]/10 px-4 py-3'
            }
        >
            {!compact && description ? (
                <p className="font-body text-sm text-[#262A22]">{description}</p>
            ) : null}
            <p
                className={[
                    'font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]',
                    compact ? '' : 'mt-3',
                ].join(' ')}
            >
                Preview calorie tier
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
                {tiers.map((tier) => (
                    <PillButton
                        key={tier}
                        label={`${tier} kcal`}
                        variant={selectedTier === tier ? 'primary' : 'outline'}
                        size="sm"
                        disabled={loading}
                        onClick={() => onSelectTier(tier)}
                        className={selectedTier === tier ? '' : 'ring-1 ring-[#E5E7EB]'}
                    />
                ))}
            </div>
            <p className="mt-2 font-body text-xs text-[#555555]">
                {loading
                    ? 'Loading Meal Tiers Library portions…'
                    : compact
                      ? compactHint
                      : `Currently testing at ${selectedTier} kcal. Your choice is remembered for this browser session.`}
            </p>
        </div>
    );
}
