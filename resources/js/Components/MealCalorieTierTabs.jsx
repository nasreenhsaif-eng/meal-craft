import { useMemo, useState } from 'react';
import MacroGrid from './MacroGrid.jsx';

/**
 * Admin Meal Tiers Library calorie tabs. Classic Meal Library does not use this.
 *
 * @param {object} props
 * @param {number[]} props.tabValues
 * @param {Array<{ calorie_tier: number; macros?: object; nutritionalData?: object; authored?: boolean }>} [props.calorieTiers]
 * @param {number | null} [props.activeTier]
 * @param {(tier: number, payload: object | null) => void} [props.onCalorieTierChange]
 */
export default function MealCalorieTierTabs({ tabValues, calorieTiers = [], onCalorieTierChange, activeTier: controlledTier }) {
    const tabs = Array.isArray(tabValues) ? tabValues : [];
    const numericTabs = tabs.map((tier) => Number(tier)).filter((tier) => Number.isFinite(tier));
    const byTier = useMemo(() => {
        /** @type {Record<number, object>} */
        const map = {};
        for (const row of calorieTiers) {
            const tier = Number(row?.calorie_tier);
            if (Number.isFinite(tier)) {
                map[tier] = row;
            }
        }
        return map;
    }, [calorieTiers]);

    const defaultTier = numericTabs.includes(500) ? 500 : (numericTabs[0] ?? null);
    const [active, setActive] = useState(Number(controlledTier) || defaultTier);

    if (numericTabs.length === 0) {
        return null;
    }

    const requestedTier = Number(controlledTier);
    const activeTier = numericTabs.includes(requestedTier)
        ? requestedTier
        : (numericTabs.includes(active) ? active : defaultTier);
    const payload = activeTier != null ? (byTier[activeTier] ?? null) : null;
    const macros = payload?.macros ?? null;
    const authored = Boolean(payload?.authored);

    return (
        <div className="mt-0 w-full min-w-0 shrink px-0">
            <div className="flex flex-wrap justify-center gap-1" role="tablist" aria-label="Calorie tiers">
                {numericTabs.map((tier) => {
                    const isActive = tier === activeTier;
                    const isAuthored = Boolean(byTier[tier]?.authored);
                    return (
                        <button
                            key={tier}
                            type="button"
                            role="tab"
                            aria-selected={isActive}
                            className={`rounded-full px-2 py-0.5 font-montserrat text-[10px] font-bold tracking-wide ${
                                isActive
                                    ? 'bg-[#5A6B44] text-white'
                                    : isAuthored
                                      ? 'bg-[#E8EDE3] text-[#262A22]'
                                      : 'bg-[#F3F3F3] text-[#888888]'
                            }`}
                            onClick={(event) => {
                                event.stopPropagation();
                                setActive(tier);
                                onCalorieTierChange?.(tier, byTier[tier] ?? null);
                            }}
                        >
                            {tier}
                        </button>
                    );
                })}
            </div>
            {authored && macros ? (
                <div className="mt-1">
                    <MacroGrid
                        calories={macros.calories}
                        protein={macros.protein}
                        carbs={macros.carbs}
                        fat={macros.fat}
                        compact
                        fluid
                        abbreviated={false}
                        className="!w-full !max-w-full min-w-0"
                    />
                </div>
            ) : (
                <p className="mt-2 text-center font-body text-[11px] text-[#888888]">Not designed yet</p>
            )}
        </div>
    );
}
