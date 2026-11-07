<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_craft_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('plan_weeks')->default(4)->after('week_duration');
        });
    }

    public function down(): void
    {
        Schema::table('customer_craft_plans', function (Blueprint $table) {
            $table->dropColumn('plan_weeks');
        });
    }
};
