<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_profiles')
            ->where('onboarding_step', 'welcome')
            ->update(['onboarding_step' => 'gender']);
    }

    public function down(): void
    {
        // Legacy welcome step removed from the live wizard — no safe rollback.
    }
};
