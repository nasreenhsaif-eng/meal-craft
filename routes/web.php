<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminUserActionController;
use App\Http\Controllers\Admin\CustomerProfileController;
use App\Http\Controllers\Admin\IngredientLibraryController;
use App\Http\Controllers\Admin\IngredientLibraryCsvExportController;
use App\Http\Controllers\Admin\IngredientLibraryCsvImportController;
use App\Http\Controllers\Admin\KitchenLogisticsController;
use App\Http\Controllers\Admin\MealController;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Http\Controllers\Admin\MealLibraryCsvImportController;
use App\Http\Controllers\Admin\MealPlanLibraryController;
use App\Http\Controllers\Api\AdaptedMenuController;
use App\Http\Controllers\Api\CustomerCraftPlanController;
use App\Http\Controllers\Auth\PortalChoiceController;
use App\Http\Controllers\Auth\WelcomeController;
use App\Http\Controllers\Customer\ConsultationCraftedForYouController;
use App\Http\Controllers\Customer\CustomerAppController;
use App\Http\Controllers\Customer\OnboardingController;
use App\Http\Controllers\MealLibraryCsvExportController;
use App\Http\Controllers\MealLibraryCsvImportController as JsonMealLibraryCsvImportController;
use App\Models\Meal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $user = $request->user();

    if ($user !== null) {
        return redirect($user->homePath());
    }

    return redirect()->route('login');
})->name('home');

Route::get('/welcome', [WelcomeController::class, 'show'])->name('welcome');

Route::middleware('guest')->group(function (): void {
    Route::view('/join', 'pages::auth.join')->name('join');
});

Route::view('/sign-out', 'pages::auth.sign-out')->name('sign-out');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/login/portal-choice', [PortalChoiceController::class, 'show'])
        ->middleware('portal.choice')
        ->name('login.portal-choice');

    Route::get('dashboard', function () {
        $user = auth()->user();

        return redirect($user?->homePath() ?? route('login', absolute: false));
    })->name('dashboard');

    Route::middleware('admin')->group(function (): void {
        Route::prefix('admin')
            ->name('admin.')
            ->group(function (): void {
                Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
                Route::get('/ingredient-library', [IngredientLibraryController::class, 'index'])->name('ingredient-library');
                Route::get('/ingredient-library/export-csv', IngredientLibraryCsvExportController::class)->name('ingredient-library.export-csv');
                Route::post('/ingredient-library/import-csv', IngredientLibraryCsvImportController::class)->name('ingredient-library.import-csv');
                Route::post('/ingredient-library/base-ingredient', [IngredientLibraryController::class, 'store'])->name('ingredient-library.base-ingredient.store');
                Route::post('/ingredient-library/base-ingredient/{ingredient}', [IngredientLibraryController::class, 'update'])->name('ingredient-library.base-ingredient.update');
                Route::post('/ingredient-library', [IngredientLibraryController::class, 'store'])->name('ingredient-library.store');
                Route::post('/ingredient-library/bulk-destroy', [IngredientLibraryController::class, 'bulkDestroy'])->name('ingredient-library.bulk-destroy');
                Route::post('/ingredient-library/{ingredient}', [IngredientLibraryController::class, 'update'])->name('ingredient-library.update');
                Route::get('/meal-library', [MealLibraryController::class, 'index'])->name('meal-library');
                Route::get('/meal-library/csv-template', [MealLibraryController::class, 'downloadMealCraftCsvTemplate'])->name('meal-library.csv-template');
                Route::post('/meal-library/import-csv', MealLibraryCsvImportController::class)->name('meal-library.import-csv');
                Route::post('/meal-library', [MealController::class, 'store'])->name('meal-library.store');
                Route::post('/meal-library/bulk-destroy', [MealLibraryController::class, 'bulkDestroy'])->name('meal-library.bulk-destroy');
                Route::post('/meal-library/reorder', [MealLibraryController::class, 'reorder'])->name('meal-library.reorder');
                Route::post('/meal-library/{meal}', [MealController::class, 'update'])->name('meal-library.update');
                Route::get('/meal-plan-library', [MealPlanLibraryController::class, 'index'])->name('meal-plan-library');
                Route::post('/meal-plan-library', [MealPlanLibraryController::class, 'store'])->name('meal-plan-library.store');
                Route::get('/meal-plan-library/meals/search', [MealPlanLibraryController::class, 'searchMeals'])->name('meal-plan-library.meals.search');
                Route::get('/meal-plan-library/{mealPlan}', [MealPlanLibraryController::class, 'show'])->name('meal-plan-library.show');
                Route::get('/customers', [CustomerProfileController::class, 'index'])->name('customers');
                Route::get('/kitchen-logistics', [KitchenLogisticsController::class, 'index'])->name('kitchen-logistics');

                Route::prefix('settings')->name('settings.')->group(function (): void {
                    Route::redirect('/', '/admin/settings/profile')->name('index');
                    Route::get('/profile', [AdminSettingsController::class, 'editProfile'])->name('profile');
                    Route::patch('/profile', [AdminSettingsController::class, 'updateProfile'])->name('profile.update');
                    Route::get('/security', [AdminSettingsController::class, 'editSecurity'])->name('security');
                    Route::put('/security/password', [AdminSettingsController::class, 'updatePassword'])->name('security.password');
                    Route::get('/appearance', [AdminSettingsController::class, 'editAppearance'])->name('appearance');
                });

                Route::post('/users/{user}/toggle-active', [AdminUserActionController::class, 'toggleActive'])->name('users.toggle-active');
                Route::post('/users/{user}/password-reset', [AdminUserActionController::class, 'sendPasswordReset'])->name('users.password-reset');
            });

        Route::livewire('ingredients', 'pages::ingredients')->name('ingredients.index');
        Route::livewire('meals', 'pages::meals')->name('meals.index');
        Route::livewire('meals/{meal}/edit', 'pages::meals')->name('meals.edit');
        Route::post('meals/library/import-csv', JsonMealLibraryCsvImportController::class)->name('meals.library.import-csv');
        Route::get('meals/library/export-csv', MealLibraryCsvExportController::class)->name('meals.library.export-csv');
        Route::livewire('meal-plans', 'pages::meal-plans')->name('meal-plans.index');
        Route::livewire('meal-plans/four-week', 'pages::meal-plans-four-week')->name('meal-plans.four-week');

        Route::redirect('recipes', '/meals')->name('recipes.redirect.index');
        Route::redirect('recipes/create', '/meals')->name('recipes.redirect.create');
        Route::get('recipes/{meal}/edit', function (Meal $meal) {
            return redirect()->route('meals.edit', $meal);
        })->name('recipes.redirect.edit');
    });

    Route::middleware('customer')->group(function (): void {
        Route::post('/onboarding/reset', [OnboardingController::class, 'resetForTesting'])
            ->name('onboarding.reset');

        Route::prefix('onboarding')
            ->name('onboarding.')
            ->middleware('onboarding.incomplete')
            ->group(function (): void {
                Route::get('/', [OnboardingController::class, 'index'])->name('index');
                Route::get('/{step}', [OnboardingController::class, 'show'])
                    ->middleware('onboarding.step')
                    ->name('show');
                Route::post('/gender', [OnboardingController::class, 'storeGender'])->name('gender.store');
                Route::post('/period-tracking', [OnboardingController::class, 'storePeriodTracking'])->name('period-tracking.store');
                Route::post('/birthday', [OnboardingController::class, 'storeBirthday'])->name('birthday.store');
                Route::post('/height', [OnboardingController::class, 'storeHeight'])->name('height.store');
                Route::post('/weight', [OnboardingController::class, 'storeWeight'])->name('weight.store');
                Route::post('/target-weight', [OnboardingController::class, 'storeTargetWeight'])->name('target-weight.store');
                Route::post('/activity', [OnboardingController::class, 'storeActivity'])->name('activity.store');
                Route::post('/diet-protocol', [OnboardingController::class, 'storeDietProtocol'])->name('diet-protocol.store');
                Route::post('/daily-targets', [OnboardingController::class, 'storeDailyTargets'])->name('daily-targets.store');
                Route::post('/food-filters', [OnboardingController::class, 'storeFoodFilters'])->name('food-filters.store');
            });

        Route::prefix('app')
            ->name('app.')
            ->group(function (): void {
                Route::get('/meal-plan', [CustomerAppController::class, 'mealPlan'])
                    ->name('meal-plan');

                Route::middleware('onboarding.complete')->group(function (): void {
                    Route::get('/', [CustomerAppController::class, 'home'])->name('home');
                });
            });

        Route::prefix('api')->group(function (): void {
            Route::get('/menu/adapted', AdaptedMenuController::class)->name('api.menu.adapted');
            Route::post('/customer/craft-plan', [CustomerCraftPlanController::class, 'store'])
                ->name('api.customer.craft-plan.store');
        });

        Route::get('consultation/crafted-for-you', ConsultationCraftedForYouController::class)
            ->name('consultation.crafted-for-you');
    });
});

require __DIR__.'/settings.php';
