<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DietTag;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IngredientLibraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/IngredientsLibrary', [
            'dietTags' => DietTag::toDropdownOptions(),
        ]);
    }
}
