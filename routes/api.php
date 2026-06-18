<?php

use App\Http\Controllers\Api\Admin\KitchenDailySheetController;
use App\Http\Controllers\Api\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('api.onboarding.store');
    Route::get('/admin/kitchen/daily-sheet', KitchenDailySheetController::class)->name('api.admin.kitchen.daily-sheet');
});
