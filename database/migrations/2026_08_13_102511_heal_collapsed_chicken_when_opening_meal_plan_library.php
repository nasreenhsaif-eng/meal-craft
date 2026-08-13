<?php

use App\Services\CollapsedPrimaryProteinHealer;
use Illuminate\Database\Migrations\Migration;

/**
 * Admin meal-plan library still showed 1g chicken macros from unhealed rows.
 * Re-run library-wide heal on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(CollapsedPrimaryProteinHealer::class)->healAll();
    }

    public function down(): void
    {
        // Data repair — not reversible.
    }
};
