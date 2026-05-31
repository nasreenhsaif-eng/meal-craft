<?php

use App\Http\Controllers\Api\AdaptedMenuController;
use App\Http\Controllers\Api\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('api.onboarding.store');
    Route::get('/menu/adapted', AdaptedMenuController::class)->name('api.menu.adapted');
});
