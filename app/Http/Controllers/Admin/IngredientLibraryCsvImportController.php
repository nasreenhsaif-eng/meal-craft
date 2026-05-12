<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\IngredientsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IngredientLibraryCsvImportController extends Controller
{
    public function __invoke(Request $request, IngredientsImport $ingredientsImport): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $count = $ingredientsImport->import($validated['file']);

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', __('Import complete: :n ingredient rows processed.', ['n' => $count]));
    }
}
