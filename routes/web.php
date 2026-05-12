<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserActionController;
use App\Http\Controllers\Admin\IngredientLibraryController;
use App\Http\Controllers\Admin\IngredientLibraryCsvExportController;
use App\Http\Controllers\Admin\IngredientLibraryCsvImportController;
use App\Http\Controllers\Admin\MealController;
use App\Http\Controllers\Admin\MealLibraryController;
use App\Http\Controllers\Admin\MealPlanLibraryController;
use App\Http\Controllers\MealLibraryCsvExportController;
use App\Http\Controllers\MealLibraryCsvImportController;
use App\Models\Meal;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/admin/dashboard')->name('dashboard');

    // Inertia admin UI (requires auth + verified via this parent group).
    // Route names: admin.dashboard, admin.ingredient-library, admin.meal-library, admin.meal-plan-library, …
    // (kebab-case suffixes match URLs for Ziggy / route() in JS if added later.)
    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('/ingredient-library', [IngredientLibraryController::class, 'index'])->name('ingredient-library');
            Route::get('/ingredient-library/export-csv', IngredientLibraryCsvExportController::class)->name('ingredient-library.export-csv');
            Route::post('/ingredient-library/import-csv', IngredientLibraryCsvImportController::class)->name('ingredient-library.import-csv');
            Route::get('/meal-library', [MealLibraryController::class, 'index'])->name('meal-library');
            Route::post('/meal-library', [MealController::class, 'store'])->name('meal-library.store');
            Route::get('/meal-plan-library', [MealPlanLibraryController::class, 'index'])->name('meal-plan-library');

            Route::post('/users/{user}/toggle-active', [AdminUserActionController::class, 'toggleActive'])->name('users.toggle-active');
            Route::post('/users/{user}/password-reset', [AdminUserActionController::class, 'sendPasswordReset'])->name('users.password-reset');
        });
    Route::view('consultation/crafted-for-you', 'pages.consultation.crafted-for-you')->name('consultation.crafted-for-you');
    Route::livewire('ingredients', 'pages::ingredients')->name('ingredients.index');
    Route::livewire('meals', 'pages::meals')->name('meals.index');
    Route::livewire('meals/{meal}/edit', 'pages::meals')->name('meals.edit');
    Route::post('meals/library/import-csv', MealLibraryCsvImportController::class)->name('meals.library.import-csv');
    Route::get('meals/library/export-csv', MealLibraryCsvExportController::class)->name('meals.library.export-csv');
    Route::livewire('meal-plans', 'pages::meal-plans')->name('meal-plans.index');
    Route::livewire('meal-plans/four-week', 'pages::meal-plans-four-week')->name('meal-plans.four-week');

    Route::redirect('recipes', '/meals')->name('recipes.redirect.index');
    Route::redirect('recipes/create', '/meals')->name('recipes.redirect.create');
    Route::get('recipes/{meal}/edit', function (Meal $meal) {
        return redirect()->route('meals.edit', $meal);
    })->name('recipes.redirect.edit');
});

require __DIR__.'/settings.php';
