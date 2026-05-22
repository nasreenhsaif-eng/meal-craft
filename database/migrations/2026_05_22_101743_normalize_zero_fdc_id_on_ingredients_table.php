<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('ingredients')
            ->where('fdc_id', 0)
            ->update(['fdc_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible: multiple rows may have had fdc_id 0 before normalization.
    }
};
