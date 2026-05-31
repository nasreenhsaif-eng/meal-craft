<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_profiles')
            ->where('onboarding_step', 'goals')
            ->update(['onboarding_step' => 'activity']);
    }

    public function down(): void
    {
        DB::table('customer_profiles')
            ->where('onboarding_step', 'activity')
            ->update(['onboarding_step' => 'goals']);
    }
};
