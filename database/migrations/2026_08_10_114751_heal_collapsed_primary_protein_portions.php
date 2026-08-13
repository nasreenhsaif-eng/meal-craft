<?php

use App\Services\CollapsedPrimaryProteinHealer;
use App\Services\MenuDevelopmentCsvSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Production still has sodium-refiner collapses (1–2 g chicken) and UI-locked salads
 * that lost chicken entirely. Heal on migrate so a refresh after deploy cannot keep
 * those broken portions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $healed = app(CollapsedPrimaryProteinHealer::class)->healAll();

        if ($healed !== []) {
            app(MenuDevelopmentCsvSync::class)->syncMealsFromDatabase();
        }
    }

    public function down(): void
    {
        // Data repair — not reversible.
    }
};
