<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealFromLibraryRequest;
use App\Models\Meal;
use Illuminate\Http\RedirectResponse;

/**
 * HTTP entry for creating meals from the admin library UI.
 * Persistence lives on {@see MealLibraryController::store}; this class keeps a dedicated controller file for routes and DI.
 */
class MealController extends Controller
{
    public function store(StoreMealFromLibraryRequest $request, MealLibraryController $mealLibrary): RedirectResponse
    {
        return $mealLibrary->store($request);
    }

    public function update(StoreMealFromLibraryRequest $request, Meal $meal, MealLibraryController $mealLibrary): RedirectResponse
    {
        return $mealLibrary->update($request, $meal);
    }
}
