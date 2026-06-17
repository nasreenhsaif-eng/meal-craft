<?php

use App\Http\Controllers\Api\AdaptedMenuController;
use App\Http\Controllers\Api\Admin\KitchenDailySheetController;
use App\Http\Controllers\Api\CustomerCraftPlanController;
use App\Http\Controllers\Api\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('api.onboarding.store');
    Route::get('/menu/adapted', AdaptedMenuController::class)->name('api.menu.adapted');
    Route::post('/customer/craft-plan', [CustomerCraftPlanController::class, 'store'])->name('api.customer.craft-plan.store');
    Route::get('/admin/kitchen/daily-sheet', KitchenDailySheetController::class)->name('api.admin.kitchen.daily-sheet');
});
