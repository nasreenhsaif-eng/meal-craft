<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->text('description')->nullable()->after('goal');
            $table->date('published_starts_on')->nullable()->after('default_day_selections');
            $table->date('published_ends_on')->nullable()->after('published_starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'published_starts_on', 'published_ends_on']);
        });
    }
};
