<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            if (! Schema::hasColumn('meals', 'food_filter_tags')) {
                $table->json('food_filter_tags')->nullable()->after('diet_tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            if (Schema::hasColumn('meals', 'food_filter_tags')) {
                $table->dropColumn('food_filter_tags');
            }
        });
    }
};
