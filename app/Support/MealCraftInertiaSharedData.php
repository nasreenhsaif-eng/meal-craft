<?php

namespace App\Support;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerGoal;
use App\Enums\CustomerSex;
use App\Enums\CyclePhase;
use App\Enums\DietTag;
use App\Enums\DietType;
use App\Enums\MacroSplitStyle;
use App\Enums\OnboardingStep;
use App\Enums\RecipeCategory;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\MealCsvLibraryImportService;
use App\Services\Nutrition\OnboardingDailyTargetsCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Global Inertia props for Meal Craft admin library workflows and customer onboarding.
 *
 * Shared from {@see HandleInertiaRequests} for authenticated users.
 */
final class MealCraftInertiaSharedData
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequest(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $data = [];

        if ($user->isAdmin()) {
            $data = [
                'urls' => self::adminUrls(),
                'constants' => self::constants(),
                'taxonomy' => self::adminTaxonomy(),
                'csv' => self::csv(),
                'notices' => [
                    'mealLibrarySchema' => self::mealLibrarySchemaNotice(),
                ],
            ];
        }

        $data['onboarding'] = self::onboarding($user);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function onboarding(User $user): array
    {
        $profile = $user->customerProfile;

        return [
            'steps' => array_map(
                static fn (OnboardingStep $step): array => [
                    'value' => $step->value,
                    'label' => $step->label(),
                ],
                OnboardingStep::orderedFor($profile),
            ),
            'currentStep' => $user->currentOnboardingStep()->value,
            'completed' => $user->hasCompletedOnboarding(),
            'customerName' => $user->name,
            'urls' => self::onboardingUrls(),
            'options' => self::onboardingOptions(),
            'profile' => self::profileSnapshot($profile),
            'computedTargets' => self::computedTargetsSnapshot($profile),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function adminUrls(): array
    {
        return [
            'ingredientLibrary' => [
                'index' => route('admin.ingredient-library'),
                'store' => route('admin.ingredient-library.store'),
                'baseStore' => route('admin.ingredient-library.base-ingredient.store'),
                'importCsv' => route('admin.ingredient-library.import-csv'),
                'exportCsv' => route('admin.ingredient-library.export-csv'),
                'bulkDestroy' => route('admin.ingredient-library.bulk-destroy'),
                'template' => asset('templates/ingredients-library-template.csv'),
            ],
            'mealLibrary' => [
                'index' => route('admin.meal-library'),
                'store' => route('admin.meal-library.store'),
                'bulkDestroy' => route('admin.meal-library.bulk-destroy'),
                'reorder' => route('admin.meal-library.reorder'),
                'mealCraftTemplate' => route('admin.meal-library.csv-template'),
                'importCsv' => route('admin.meal-library.import-csv'),
                'exportCsv' => route('meals.library.export-csv'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function onboardingUrls(): array
    {
        return [
            'index' => route('onboarding.index'),
            'gender' => route('onboarding.gender.store'),
            'periodTracking' => route('onboarding.period-tracking.store'),
            'birthday' => route('onboarding.birthday.store'),
            'height' => route('onboarding.height.store'),
            'weight' => route('onboarding.weight.store'),
            'targetWeight' => route('onboarding.target-weight.store'),
            'activity' => route('onboarding.activity.store'),
            'dietProtocol' => route('onboarding.diet-protocol.store'),
            'dailyTargets' => route('onboarding.daily-targets.store'),
            'foodFilters' => route('onboarding.food-filters.store'),
            'reset' => route('onboarding.reset'),
            'appHome' => route('app.home'),
            'consultation' => route('consultation.crafted-for-you'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function onboardingOptions(): array
    {
        return [
            'sex' => EnumDropdownOptions::fromBackedEnum(CustomerSex::class),
            'activityLevels' => EnumDropdownOptions::fromBackedEnum(CustomerActivityLevel::class),
            'goals' => CustomerGoal::toDropdownOptions(),
            'dietTypes' => DietType::toDropdownOptions(),
            'macroSplitStyles' => [
                ['value' => MacroSplitStyle::Balanced->value, 'label' => __('Balanced')],
                ['value' => MacroSplitStyle::HighProtein->value, 'label' => __('High protein')],
            ],
            'allergens' => self::allergenOptions(),
            'dislikes' => CustomerDislikeCatalog::toDropdownOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function allergenOptions(): array
    {
        $options = [];

        foreach (IngredientAllergenCatalog::labelsBySlug() as $slug => $label) {
            $options[] = [
                'value' => $slug,
                'label' => (string) str_replace('Contains: ', '', $label),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function profileSnapshot(?CustomerProfile $profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        return [
            'weightKg' => $profile->weight_kg,
            'targetWeightKg' => $profile->target_weight_kg,
            'heightCm' => $profile->height_cm,
            'age' => $profile->age,
            'dateOfBirth' => $profile->date_of_birth?->toDateString(),
            'birthdate' => $profile->birthdate?->toDateString() ?? $profile->date_of_birth?->toDateString(),
            'sex' => $profile->sex?->value,
            'gender' => $profile->gender ?? $profile->sex?->value,
            'activityLevel' => $profile->activity_level?->value,
            'goal' => $profile->goal?->value,
            'dietType' => $profile->diet_type?->value,
            'dietProtocol' => $profile->diet_protocol,
            'diet_protocol' => $profile->diet_protocol,
            'macroSplitStyle' => $profile->macro_split_style?->value,
            'dailyCalorieTarget' => $profile->daily_calorie_target,
            'allergies' => $profile->allergies ?? [],
            'foodFilters' => $profile->food_filters ?? $profile->allergies ?? [],
            'food_filters' => $profile->food_filters ?? $profile->allergies ?? [],
            'dislikes' => $profile->dislikes ?? [],
            'loggedPeriods' => $profile->logged_periods ?? [],
            'logged_periods' => $profile->logged_periods ?? [],
            'periodTrackingData' => $profile->period_tracking_data ?? [],
            'period_tracking_data' => $profile->period_tracking_data ?? [],
            'averageCycleLength' => $profile->average_cycle_length,
            'average_cycle_length' => $profile->average_cycle_length,
            'weight_kg' => $profile->weight_kg,
            'target_weight_kg' => $profile->target_weight_kg,
            'height_cm' => $profile->height_cm,
            'age' => $profile->age,
            'date_of_birth' => $profile->date_of_birth?->toDateString(),
            'activity_level' => $profile->activity_level?->value,
            'macro_split_style' => $profile->macro_split_style?->value,
            'daily_calorie_target' => $profile->daily_calorie_target,
            'protein_percentage' => $profile->protein_percentage,
            'carb_percentage' => $profile->carb_percentage,
            'fat_percentage' => $profile->fat_percentage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function computedTargetsSnapshot(?CustomerProfile $profile): ?array
    {
        if ($profile === null || $profile->daily_calorie_target === null) {
            return null;
        }

        if ($profile->weight_kg === null || $profile->height_cm === null) {
            return null;
        }

        $targets = OnboardingDailyTargetsCalculator::calculate($profile);

        return [
            'bmr' => $targets['bmr'],
            'tdee' => $targets['tdee'],
            'dailyCalories' => $targets['daily_calories'],
            'proteinGrams' => $targets['protein_grams'],
            'carbGrams' => $targets['carb_grams'],
            'fatGrams' => $targets['fat_grams'],
            'proteinPercentage' => $targets['protein_percentage'],
            'carbPercentage' => $targets['carb_percentage'],
            'fatPercentage' => $targets['fat_percentage'],
            'goal' => $targets['goal'],
            'dietProtocol' => $targets['diet_protocol'],
            'currentPhase' => $targets['current_phase'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function constants(): array
    {
        return [
            'missingPhotoPlaceholder' => MealImagePath::MISSING_PHOTO_PLACEHOLDER,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function adminTaxonomy(): array
    {
        return [
            'mealCategories' => array_map(
                static fn (RecipeCategory $category): string => $category->value,
                MealCsvLibraryImportService::mealLibraryCsvAllowedCategories(),
            ),
            'mealPlanTags' => MealLibraryTaxonomy::MEAL_PLAN_TAGS,
            'dietaryTags' => MealLibraryTaxonomy::DIETARY_TAGS,
            'dietTags' => DietTag::toDropdownOptions(),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
            'preparedIngredientCategories' => IngredientLibraryCategory::preparedLabels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function csv(): array
    {
        return [
            'masterMealHeaders' => MealCsvHeaderCatalog::MASTER_HEADERS,
            'libraryMealHeaders' => MealCsvLibraryImportService::LIBRARY_CSV_HEADERS,
        ];
    }

    public static function mealLibrarySchemaNotice(): ?string
    {
        try {
            $ready = Schema::hasTable('meals')
                && Schema::hasTable('ingredients')
                && Schema::hasColumn('meals', 'library_sort_order')
                && Schema::hasColumn('meals', 'meal_plan_tags')
                && Schema::hasColumn('meals', 'cycle_phases')
                && Schema::hasColumn('meals', 'safety_alert_tags')
                && Schema::hasColumn('meals', 'nutrition_aggregates_synced')
                && Schema::hasColumn('ingredients', 'common_allergens')
                && Schema::hasColumn('ingredients', 'is_g6pd_trigger');
        } catch (\Throwable) {
            $ready = false;
        }

        if ($ready) {
            return null;
        }

        return (string) __('Database update required: run `php artisan migrate` in the project root, then refresh this page.');
    }
}
