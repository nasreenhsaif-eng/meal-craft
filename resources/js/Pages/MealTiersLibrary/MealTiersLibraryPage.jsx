import { createPortal } from 'react-dom';
import { useCallback, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import MealBrowseTabs from '../../Components/MealBrowseTabs.jsx';
import MealCard from '../../Components/MealCard.jsx';
import MealCalorieTierTabs from '../../Components/MealCalorieTierTabs.jsx';
import MealIngredientRowsEditor from '../../Components/MealPlan/MealIngredientRowsEditor.jsx';
import MealDetailView from '../../Components/Molecules/MealDetailView/MealDetailView';
import BaseRecipeDetailModal from '../../Components/Molecules/BaseRecipeDetailModal/BaseRecipeDetailModal';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';

const EMPTY_ROW = Object.freeze({
    nameQuery: '',
    selectedName: '',
    ingredientId: null,
    amount: '100',
    unit: 'g',
});

function tabValuesForCategory(category, title = '', proteinStructures = {}) {
    const name = String(title ?? '').toLowerCase();
    if (name.includes('chia')) {
        return [];
    }
    if (category === 'Breakfast') {
        const breakfast = proteinStructures?.breakfast_calorie_tiers;
        return Array.isArray(breakfast) && breakfast.length > 0 ? breakfast.map(Number) : [300, 400, 500];
    }
    if (category === 'Meal' || category === 'Main Salad') {
        const mains = proteinStructures?.main_calorie_tiers;
        return Array.isArray(mains) && mains.length > 0 ? mains.map(Number) : [400, 500, 550, 600, 700, 800];
    }
    return [];
}

function rowsFromIngredients(ingredients) {
    if (!Array.isArray(ingredients) || ingredients.length === 0) {
        return [{ ...EMPTY_ROW }];
    }

    return ingredients.map((ingredient) => ({
        nameQuery: ingredient.name ?? '',
        selectedName: ingredient.name ?? '',
        ingredientId: ingredient.ingredient_id ?? null,
        amount: String(ingredient.amount_grams ?? ''),
        unit: 'g',
    }));
}

function ingredientsFromRows(rows) {
    return rows
        .filter((row) => row.ingredientId)
        .map((row) => ({
            ingredient_id: row.ingredientId,
            amount_grams: Number(row.amount) || 0,
        }));
}

function scaleIngredientRows(rows, fromTier, toTier) {
    if (!Array.isArray(rows) || fromTier <= 0) {
        return [{ ...EMPTY_ROW }];
    }

    const scale = toTier / fromTier;

    return rows.map((row) => ({
        ...row,
        amount: row.amount === '' || row.amount == null ? row.amount : String(Math.round(Number(row.amount) * scale)),
    }));
}

function bucketHint(buckets, proteinGrams, eggCount) {
    if (!buckets && !proteinGrams && !eggCount) {
        return null;
    }
    const parts = [];
    if (proteinGrams) {
        parts.push(`${proteinGrams} g protein`);
    }
    if (buckets) {
        parts.push(
            `protein ${buckets.protein} kcal · carbs+veg ${buckets.carbs_veg} kcal · sauce fat ${buckets.sauce_fat} kcal · seasoning ${buckets.seasoning} kcal`,
        );
    }
    if (eggCount) {
        parts.push(`at least ${eggCount} eggs`);
    }
    return parts.join(' · ');
}

export default function MealTiersLibraryPage({
    meals = [],
    ingredientProfiles = [],
    urls = {},
    categoryOptions = [],
    proteinStructures = {},
    browseTabs = [],
}) {
    const page = usePage();
    const flashSuccess = page.props?.flash?.success ?? page.props?.flash?.message ?? null;
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingMeal, setEditingMeal] = useState(null);
    const [detailMeal, setDetailMeal] = useState(null);
    const [detailTier, setDetailTier] = useState(null);
    const [baseRecipeModal, setBaseRecipeModal] = useState(null);
    const [browseTab, setBrowseTab] = useState(browseTabs[0]?.id ?? 'all');
    const [name, setName] = useState('');
    const [category, setCategory] = useState('Meal');
    const [description, setDescription] = useState('');
    const [activeTab, setActiveTier] = useState(500);
    const [rowsByTab, setRowsByTab] = useState(/** @type {Record<number, typeof EMPTY_ROW[]>} */ ({}));
    const [singleRows, setSingleRows] = useState([{ ...EMPTY_ROW }]);

    const tabValues = useMemo(
        () => tabValuesForCategory(category, name, proteinStructures),
        [category, name, proteinStructures],
    );
    const usesTabs = tabValues.length > 0;
    const families = proteinStructures?.families ?? {};
    const activeBrowseTab = browseTabs.some((tab) => tab.id === browseTab)
        ? browseTab
        : (browseTabs[0]?.id ?? 'all');
    const visibleMeals =
        activeBrowseTab === 'all'
            ? meals
            : meals.filter((meal) => meal.browseTab === activeBrowseTab);
    const activeBrowseLabel = browseTabs.find((tab) => tab.id === activeBrowseTab)?.label ?? 'All';

    const openEdit = (meal) => {
        setEditingMeal(meal);
        setName(meal.title ?? '');
        setCategory(meal.category ?? 'Meal');
        setDescription(meal.description ?? '');
        const tabs = Array.isArray(meal.tabValues)
            ? meal.tabValues
            : tabValuesForCategory(meal.category, meal.title, proteinStructures);
        const nextRows = {};
        for (const tier of meal.calorieTiers ?? []) {
            nextRows[tier.calorie_tier] = rowsFromIngredients(tier.ingredients ?? []);
        }
        setRowsByTab(nextRows);
        setSingleRows(rowsFromIngredients(meal.ingredients ?? []));
        setActiveTier(tabs.includes(500) ? 500 : (tabs[0] ?? 500));
        setEditorOpen(true);
    };

    const submitEditor = () => {
        const payload = {
            name,
            category,
            description,
        };

        if (usesTabs) {
            const rowsAt500 = rowsByTab[500] ?? rowsByTab[tabValues[0]] ?? [{ ...EMPTY_ROW }];
            payload.calorie_tiers = tabValues.map((tier) => {
                const rows = rowsByTab[tier];
                const hasIngredients = Array.isArray(rows) && rows.some((row) => row.ingredientId);
                return {
                    calorie_tier: tier,
                    ingredients: ingredientsFromRows(
                        hasIngredients ? rows : scaleIngredientRows(rowsAt500, 500, tier),
                    ),
                };
            });
            payload.ingredients =
                payload.calorie_tiers.find((tier) => tier.calorie_tier === 500)?.ingredients ??
                payload.calorie_tiers[0]?.ingredients ??
                [];
        } else {
            payload.ingredients = ingredientsFromRows(singleRows);
        }

        if (editingMeal) {
            router.post(`${urls.index}/${editingMeal.id}`, payload);
        } else {
            router.post(urls.store, payload);
        }
        setEditorOpen(false);
    };

    const mapTierIngredientItems = (rows = []) =>
        rows
            .filter((row) => Boolean(row?.line))
            .map((row) => ({
                line: String(row.line),
                ingredientId: Number(row.ingredient_id ?? row.ingredientId ?? 0) || undefined,
                isBaseRecipe: Boolean(row.is_base_recipe ?? row.isBaseRecipe),
            }));

    const detailViewForMeal = (meal) => {
        if (!detailTier || !Array.isArray(meal.calorieTiers)) {
            return meal.detailView;
        }
        const tier = meal.calorieTiers.find((row) => Number(row.calorie_tier) === Number(detailTier));
        if (!tier) {
            return meal.detailView;
        }
        const ingredientItems = mapTierIngredientItems(tier.ingredients ?? []);
        const ingredientLines = ingredientItems.map((row) => row.line).filter(Boolean);

        return {
            ...meal.detailView,
            shortDescription: '',
            description: '',
            nutritionalData: tier.nutritionalData,
            ingredients: ingredientLines.length > 0 ? ingredientLines : meal.detailView?.ingredients,
            ingredientItems: ingredientItems.length > 0 ? ingredientItems : meal.detailView?.ingredientItems,
            ingredientSections: tier.ingredientSections ?? meal.detailView?.ingredientSections,
            ingredientsPrepNote:
                tier.ingredientsPrepNote ?? meal.detailView?.ingredientsPrepNote,
            cookingYieldNote: tier.cookingYieldNote ?? meal.detailView?.cookingYieldNote,
        };
    };

    const handleBaseRecipeClick = useCallback((item) => {
        if (!item?.ingredientId) {
            return;
        }

        setBaseRecipeModal({
            ingredientId: item.ingredientId,
            title: 'Base recipe',
        });
    }, []);

    const activeBucketHint = (() => {
        if (!usesTabs) {
            return null;
        }
        const familyKey =
            editingMeal?.proteinFamily ??
            (String(name).toLowerCase().includes('liver') || String(name).toLowerCase().includes('chicken')
                ? 'chicken'
                : String(name).toLowerCase().includes('beef')
                  ? 'beef'
                  : String(name).toLowerCase().includes('salmon') || String(name).toLowerCase().includes('fish')
                    ? 'fish'
                    : null);
        const buckets = familyKey ? families?.[familyKey]?.[activeTab] : null;
        const proteinGrams = proteinStructures?.protein_grams?.[activeTab];
        const eggCount =
            category === 'Breakfast' ? proteinStructures?.savory_egg_counts?.[activeTab] : null;
        return bucketHint(buckets, proteinGrams, eggCount);
    })();

    return (
        <div className="flex flex-col gap-6">
            {flashSuccess ? (
                <p className="rounded-[12px] border border-[#5A6B44]/30 bg-white px-4 py-3 font-body text-sm text-[#262A22]" role="status">
                    {flashSuccess}
                </p>
            ) : null}

            <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 className="font-montserrat text-lg font-bold text-[#262A22]">Meal Tiers Library</h2>
                            <p className="mt-1 max-w-2xl font-body text-sm text-[#555555]">
                                Work calorie-tab recipes here. Mains use 400–800 tabs; savory breakfasts use 300 / 400 /
                                500; chia, sides, desserts, and soups stay one size.
                            </p>
                        </div>
                        {urls.copyAll ? (
                            <Button
                                type="button"
                                variant="secondary"
                                label="Copy all from Meal Library"
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            'Copy every Meal Library meal that is not already in Meal Tiers Library?',
                                        )
                                    ) {
                                        return;
                                    }
                                    router.post(urls.copyAll);
                                }}
                            />
                        ) : null}
                    </div>
                    <MealBrowseTabs tabs={browseTabs} activeId={activeBrowseTab} onChange={setBrowseTab} />
                </div>
            </section>

            {visibleMeals.length === 0 ? (
                <p className="font-body text-sm text-[#555555]">
                    {activeBrowseTab === 'all'
                        ? 'No meals in the Meal Tiers Library yet. Copy a meal from the Meal Library to get started.'
                        : `No ${activeBrowseLabel.toLowerCase()} meals in the Meal Tiers Library yet.`}
                </p>
            ) : (
                <div className="grid grid-cols-1 justify-items-center gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {visibleMeals.map((meal) => (
                        <MealCard
                            key={meal.id}
                            isAdmin
                            adminControls
                            showActions
                            meal={meal}
                            calorieTierTabs={meal.usesTabs ? meal.tabValues : null}
                            calorieTiers={meal.calorieTiers}
                            onEdit={() => openEdit(meal)}
                            onViewDetails={() => {
                                setDetailMeal(meal);
                                setDetailTier(meal.tabValues?.includes(500) ? 500 : (meal.tabValues?.[0] ?? null));
                            }}
                            onCalorieTierChange={(tier) => {
                                if (detailMeal?.id === meal.id) {
                                    setDetailTier(tier);
                                }
                            }}
                        />
                    ))}
                </div>
            )}

            {detailMeal && typeof document !== 'undefined'
                ? createPortal(
                      <div className="fixed inset-0 z-[100] flex items-end justify-center sm:items-center sm:p-4">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              aria-label="Close meal details"
                              onClick={() => setDetailMeal(null)}
                          />
                          <div
                              role="dialog"
                              aria-modal="true"
                              aria-labelledby="meal-tiers-detail-title"
                              className="relative flex max-h-[min(92dvh,calc(100dvh-2rem))] w-full max-w-5xl flex-col overflow-hidden rounded-t-[12px] bg-white shadow-2xl sm:rounded-[12px]"
                          >
                              <div className="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-4 py-4 md:px-6">
                                  <h3
                                      id="meal-tiers-detail-title"
                                      className="min-w-0 break-words font-montserrat text-xl font-bold tracking-tight text-[#262A22] md:text-2xl"
                                  >
                                      {detailMeal.title}
                                  </h3>
                                  <Button type="button" variant="secondary" label="Close" onClick={() => setDetailMeal(null)} />
                              </div>
                              {detailMeal.usesTabs ? (
                                  <div className="shrink-0 border-b border-gray-100 px-4 py-3 md:px-6">
                                      <MealCalorieTierTabs
                                          key={detailMeal.id}
                                          tabValues={detailMeal.tabValues}
                                          calorieTiers={detailMeal.calorieTiers}
                                          activeTier={detailTier}
                                          onCalorieTierChange={(tier) => setDetailTier(tier)}
                                      />
                                  </div>
                              ) : null}
                              <MealDetailView
                                  key={`${detailMeal.id}-${detailTier ?? 'default'}`}
                                  meal={detailViewForMeal(detailMeal)}
                                  embedded
                                  onBaseRecipeClick={handleBaseRecipeClick}
                              />
                              <div className="shrink-0 border-t border-gray-100 px-4 py-3 md:px-6">
                                  <Button
                                      type="button"
                                      variant="ghost"
                                      label="Remove from Meal Tiers Library"
                                      onClick={() => {
                                          router.delete(`${urls.index}/${detailMeal.id}`);
                                          setDetailMeal(null);
                                      }}
                                  />
                              </div>
                          </div>
                      </div>,
                      document.body,
                  )
                : null}

            <BaseRecipeDetailModal
                modal={baseRecipeModal}
                onClose={() => setBaseRecipeModal(null)}
                onBaseRecipeClick={handleBaseRecipeClick}
            />

            {editorOpen && typeof document !== 'undefined'
                ? createPortal(
                      <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              aria-label="Close editor"
                              onClick={() => setEditorOpen(false)}
                          />
                          <div className="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[12px] bg-white p-6 shadow-2xl">
                              <h3 className="font-montserrat text-xl font-bold text-[#262A22]">
                                  {editingMeal ? 'Edit tiers meal' : 'Create tiers meal'}
                              </h3>
                              <div className="mt-4 grid gap-4">
                                  <TextInput label="Name" value={name} onChange={(event) => setName(event.target.value)} />
                                  <label className="flex flex-col gap-1 font-body text-xs font-semibold text-[#555555]">
                                      Category
                                      <select
                                          className="rounded-[12px] border border-gray-200 px-3 py-2 font-body text-sm text-[#262A22]"
                                          value={category}
                                          onChange={(event) => setCategory(event.target.value)}
                                      >
                                          {categoryOptions.map((option) => (
                                              <option key={option.value} value={option.value}>
                                                  {option.label}
                                              </option>
                                          ))}
                                      </select>
                                  </label>
                                  <label className="flex flex-col gap-1 font-body text-xs font-semibold text-[#555555]">
                                      Notes
                                      <textarea
                                          className="min-h-20 rounded-[12px] border border-gray-200 px-3 py-2 font-body text-sm text-[#262A22]"
                                          value={description}
                                          onChange={(event) => setDescription(event.target.value)}
                                      />
                                  </label>
                                  {usesTabs ? (
                                      <>
                                          <div className="flex flex-wrap gap-1" role="tablist" aria-label="Edit calorie tier">
                                              {tabValues.map((tier) => (
                                                  <button
                                                      key={tier}
                                                      type="button"
                                                      className={`rounded-full px-3 py-1 font-montserrat text-xs font-bold ${
                                                          activeTab === tier
                                                              ? 'bg-[#5A6B44] text-white'
                                                              : 'bg-[#E8EDE3] text-[#262A22]'
                                                      }`}
                                                      onClick={() => {
                                                          setRowsByTab((prev) => {
                                                              const existing = prev[tier];
                                                              if (Array.isArray(existing) && existing.some((row) => row.ingredientId)) {
                                                                  return prev;
                                                              }

                                                              const sourceTier = [500, ...tabValues].find((candidate) =>
                                                                  Array.isArray(prev[candidate]) &&
                                                                  prev[candidate].some((row) => row.ingredientId),
                                                              );

                                                              if (sourceTier == null) {
                                                                  return prev;
                                                              }

                                                              return {
                                                                  ...prev,
                                                                  [tier]: scaleIngredientRows(prev[sourceTier], sourceTier, tier),
                                                              };
                                                          });
                                                          setActiveTier(tier);
                                                      }}
                                                  >
                                                      {tier}
                                                  </button>
                                              ))}
                                          </div>
                                          {activeBucketHint ? (
                                              <p className="font-body text-xs text-[#555555]">{activeBucketHint}</p>
                                          ) : null}
                                          <MealIngredientRowsEditor
                                              rows={rowsByTab[activeTab] ?? [{ ...EMPTY_ROW }]}
                                              onRowsChange={(updater) =>
                                                  setRowsByTab((prev) => ({
                                                      ...prev,
                                                      [activeTab]: updater(prev[activeTab] ?? [{ ...EMPTY_ROW }]),
                                                  }))
                                              }
                                              ingredientDatabase={ingredientProfiles}
                                              comboboxIdPrefix="tiers-ingredient"
                                              suggestPortalAttribute="data-tiers-ingredient-suggest"
                                          />
                                      </>
                                  ) : (
                                      <MealIngredientRowsEditor
                                          rows={singleRows}
                                          onRowsChange={(updater) => setSingleRows((prev) => updater(prev))}
                                          ingredientDatabase={ingredientProfiles}
                                          comboboxIdPrefix="tiers-single-ingredient"
                                          suggestPortalAttribute="data-tiers-single-ingredient-suggest"
                                      />
                                  )}
                              </div>
                              <div className="mt-6 flex gap-3">
                                  <Button type="button" variant="secondary" label="Cancel" onClick={() => setEditorOpen(false)} />
                                  <Button type="button" variant="primary" label="Save" onClick={submitEditor} />
                              </div>
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}

MealTiersLibraryPage.layout = adminInertiaLayout;
