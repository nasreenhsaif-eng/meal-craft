import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';
import MacroGrid from './MacroGrid.jsx';
import Button from './Atoms/Button.jsx';
import { ProtocolTag } from './MealSystem/ProtocolTags.jsx';
import { useMemo, useState } from 'react';

/**
 * MealPlanCard — mirrors MealCard architecture but for protocol plans.
 *
 * @param {{
 *  title: string;
 *  imageUrl?: string | null;
 *  imageAlt?: string;
 *  dailyMacros: { calories: number; protein: number; carbs: number; fat: number };
 *  tags: string[];
 *  onPrimaryAction?: () => void;
 *  primaryActionLabel?: string;
 *  className?: string;
 * }} props
 */
export default function MealPlanCard({
    title,
    imageUrl,
    imageAlt = '',
    dailyMacros,
    tags,
    onPrimaryAction,
    primaryActionLabel = 'View details',
    className = '',
}) {
    const [imageBroken, setImageBroken] = useState(false);

    const macroShape = useMemo(() => {
        return {
            calories: Math.round(dailyMacros.calories),
            protein: `${Math.round(dailyMacros.protein)}g`,
            carbs: `${Math.round(dailyMacros.carbs)}g`,
            fat: `${Math.round(dailyMacros.fat)}g`,
        };
    }, [dailyMacros]);

    const heroTag = Array.isArray(tags) && tags.length > 0 ? String(tags[0]) : null;

    return (
        <article
            className={[
                'w-full overflow-hidden rounded-[12px] border border-gray-200 bg-white shadow-sm',
                className,
            ].join(' ')}
        >
            <div className="relative w-full overflow-hidden rounded-t-[12px]">
                <div className="aspect-[4/3] w-full bg-[#F8F9F6]">
                    {imageUrl && !imageBroken ? (
                        <img
                            src={imageUrl}
                            alt={imageAlt}
                            className="h-full w-full object-cover"
                            onError={() => setImageBroken(true)}
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center">
                            <div className="scale-[0.95] opacity-90">
                                <MealCraftLogo variant="seal-sm" />
                            </div>
                        </div>
                    )}
                </div>

                {heroTag ? (
                    <div className="absolute right-3 top-3 z-10">
                        <ProtocolTag label={heroTag} className="bg-white/90 shadow-sm backdrop-blur-sm" />
                    </div>
                ) : null}
            </div>

            <div className="min-w-0 px-4 pb-6 pt-4 sm:px-5">
                <header className="min-w-0 space-y-3">
                    <h3 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">{title}</h3>
                    <div className="min-w-0 rounded-[12px] border border-gray-100 bg-[#F8F9F6] px-2 py-3 sm:px-2.5">
                        <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#555555]">
                            Daily average macros
                        </p>
                        <div className="w-full min-w-0">
                            <MacroGrid
                                calories={macroShape.calories}
                                protein={macroShape.protein}
                                carbs={macroShape.carbs}
                                fat={macroShape.fat}
                                compact
                                fluid
                                abbreviated={false}
                                className="!w-full !max-w-full min-w-0"
                            />
                        </div>
                    </div>
                </header>

                <div className="pt-4">
                    <Button
                        label={primaryActionLabel}
                        variant="primary"
                        onClick={onPrimaryAction}
                        className="w-full justify-center"
                    />
                </div>
            </div>
        </article>
    );
}

