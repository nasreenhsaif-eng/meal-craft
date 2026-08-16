<?php

use App\Services\CollapsedPrimaryProteinHealer;
use Illuminate\Database\Migrations\Migration;

/**
 * PR #4's heal migration may already be marked ran on environments that never
 * actually healed production rows (or were crushed again before deploy). Re-run
 * library-wide primary protein heal on this deploy.
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
