<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('diet_protocol', 32)->nullable()->after('diet_type');
        });

        DB::table('customer_profiles')->where('onboarding_step', 'macros')->update(['onboarding_step' => 'diet_protocol']);
        DB::table('customer_profiles')->where('onboarding_step', 'meals')->update(['onboarding_step' => 'daily_targets']);
        DB::table('customer_profiles')
            ->where('onboarding_step', 'review')
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_step' => 'food_filters']);
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn('diet_protocol');
        });
    }
};
