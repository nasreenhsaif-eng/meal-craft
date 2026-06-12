import { createPortal } from 'react-dom';
import { useCallback, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import { resolveUrl } from '../../meal-craft/mealCraftPageProps.js';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import MacroGrid from '../../Components/MacroGrid.jsx';
import { MealSlotCarousel } from '../../Components/Consultation/ChooseYourMeals.jsx';
import MealDetailView from '../../Components/Molecules/MealDetailView/MealDetailView';
import { SCHEDULER_SLOT_SECTIONS } from '../../meal-library/mealSearch.ts';

const PAGE_BG = 'bg-[#F8F9F6]';

/** @type {Record<string, 'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup'>} */
const SLOT_KEY_TO_CATEGORY = {
    breakfast: 'breakfasts',
    meal: 'meals',
    sidesalad: 'sideSalads',
    dessert: 'desserts',
    soup: 'soup',
};

const DETAIL_SECTIONS = SCHEDULER_SLOT_SECTIONS.map((section) => ({
    categoryKey: SLOT_KEY_TO_CATEGORY[section.key],
    header: section.label,
    deckSuffix: section.key,
}));

/**
 * @param {object} props
 * @param {object} props.mealPlan
 * @param {Array<{ dayNumber: number; label: string; categories: Record<string, unknown[]> }>} props.days
 * @param {string} [props.libraryUrl]
 */
export default function MealPlanDetailPage({ mealPlan, days = [], libraryUrl = '/admin/meal-plan-library' }) {
    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    const [mealDetailModal, setMealDetailModal] = useState(
        /** @type {{ title: string; detailView: object } | null} */ (null),
    );

    const activeDayData = useMemo(
        () => days.find((day) => day.dayNumber === activeDay) ?? days[0] ?? null,
        [activeDay, days],
    );

    const backUrl = resolveUrl(libraryUrl, '/admin/meal-plan-library');

    const openMealDetail = useCallback((meal) => {
        if (!meal?.detailView) {
            return;
        }
        setMealDetailModal({
            title: meal.title ?? 'Meal details',
            detailView: meal.detailView,
        });
    }, []);

    const dailyMacros = mealPlan?.dailyMacros ?? {};

    return (
        <div className={`min-h-full font-body ${PAGE_BG}`}>
            <div className="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <Link
                            href={backUrl}
                            className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                        >
                            ← Back to Meal Plan Library
                        </Link>
                        <h1 className="mt-2 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                            {mealPlan?.name ?? 'Meal plan'}
                        </h1>
                        {mealPlan?.goal ? (
                            <p className="mt-2 max-w-3xl font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                                {mealPlan.goal}
                            </p>
                        ) : null}
                        {Array.isArray(mealPlan?.tags) && mealPlan.tags.length > 0 ? (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {mealPlan.tags.map((tag) => (
                                    <span
                                        key={tag}
                                        className="rounded-full bg-[#E8EFE0] px-3 py-1 font-montserrat text-xs font-semibold text-[#5A6B44]"
                                    >
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        ) : null}
                    </div>

                    <div className="w-full shrink-0 rounded-[12px] border border-gray-200 bg-white px-4 py-3 sm:max-w-xs">
                        <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#5A6B44]">
                            Avg daily macros
                        </p>
                        <div className="mt-2">
                            <MacroGrid
                                calories={dailyMacros.calories}
                                protein={dailyMacros.protein}
                                carbs={dailyMacros.carbs}
                                fat={dailyMacros.fat}
                                compact
                                fluid
                                className="!w-full !max-w-full min-w-0"
                            />
                        </div>
                    </div>
                </div>

                <div className="sticky top-0 z-30 -mx-4 border-b border-gray-200 bg-[#F8F9F6]/95 px-4 py-3 backdrop-blur-sm sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                    <div
                        className="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        role="tablist"
                        aria-label="Meal plan days"
                    >
                        {days.map((day) => {
                            const selected = day.dayNumber === activeDay;
                            return (
                                <PillButton
                                    key={day.dayNumber}
                                    type="button"
                                    role="tab"
                                    aria-selected={selected}
                                    label={`${day.label}`}
                                    variant={selected ? 'primary' : 'outline'}
                                    size="sm"
                                    onClick={() => setActiveDay(day.dayNumber)}
                                    className={selected ? 'shrink-0' : 'shrink-0 ring-1 ring-[#E5E7EB]'}
                                />
                            );
                        })}
                    </div>
                </div>

                <AnimatePresence mode="wait" initial={false}>
                    <motion.div
                        key={activeDayData?.dayNumber ?? 'empty'}
                        initial={{ x: 24, opacity: 0 }}
                        animate={{ x: 0, opacity: 1 }}
                        exit={{ x: -24, opacity: 0 }}
                        transition={{ type: 'spring', stiffness: 260, damping: 30, mass: 0.85 }}
                        className="mt-8 space-y-10 overflow-visible pb-12"
                    >
                        {activeDayData ? (
                            DETAIL_SECTIONS.map((section, idx) => {
                                const cards = activeDayData.categories?.[section.categoryKey] ?? [];

                                return (
                                    <MealSlotCarousel
                                        key={`${activeDayData.dayNumber}-${section.categoryKey}`}
                                        title={section.header}
                                        deckScopeKey={`plan-${mealPlan?.id ?? 'x'}-day-${activeDayData.dayNumber}-${section.deckSuffix}`}
                                        sectionKey={section.categoryKey}
                                        sectionStackOrder={idx}
                                        cards={cards}
                                        selectedIds={cards.map((card) => card.id)}
                                        maxSelected={Math.max(cards.length, 1)}
                                        onSelect={() => {}}
                                        readOnly
                                        onViewDetails={openMealDetail}
                                    />
                                );
                            })
                        ) : (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-white p-8 text-center text-sm text-[#555555]">
                                No day data available for this plan.
                            </p>
                        )}
                    </motion.div>
                </AnimatePresence>
            </div>

            {mealDetailModal
                ? createPortal(
                      <div className="fixed inset-0 z-[120] flex items-end justify-center sm:items-center sm:p-4">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              onClick={() => setMealDetailModal(null)}
                              aria-label="Close meal details"
                          />
                          <div
                              role="dialog"
                              aria-modal="true"
                              aria-labelledby="meal-plan-detail-modal-title"
                              className="relative z-10 flex max-h-[min(92dvh,900px)] w-full max-w-2xl flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:rounded-[16px]"
                          >
                              <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                                  <div className="min-w-0">
                                      <h2
                                          id="meal-plan-detail-modal-title"
                                          className="font-montserrat text-lg font-bold text-[#262A22]"
                                      >
                                          {mealDetailModal.title}
                                      </h2>
                                  </div>
                                  <Button
                                      label="Close"
                                      variant="ghost"
                                      type="button"
                                      onClick={() => setMealDetailModal(null)}
                                  />
                              </div>
                              <MealDetailView
                                  meal={mealDetailModal.detailView}
                                  className="max-h-[min(78vh,calc(100dvh-11rem))]"
                              />
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}

MealPlanDetailPage.layout = adminInertiaLayout;
