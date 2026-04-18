<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->string('plan_category', 48)->default('balanced')->after('schema_type');
            $table->string('cycle_phase', 24)->nullable()->after('plan_category');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_category', 'cycle_phase']);
        });
    }
};
