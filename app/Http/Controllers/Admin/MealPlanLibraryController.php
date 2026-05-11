<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CyclePhase;
use App\Enums\DietType;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MealPlanLibraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/MealPlanLibrary', [
            'dietTypes' => DietType::toDropdownOptions(),
            'cyclePhases' => CyclePhase::toDropdownOptions(),
        ]);
    }
}
