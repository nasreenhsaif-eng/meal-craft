<?php

use App\Http\Controllers\MealLibraryCsvExportController;
use App\Http\Controllers\MealLibraryCsvImportController;
use App\Models\Meal;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'pages.dashboard')->name('dashboard');
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
