<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->json('common_allergens')->nullable()->after('diet_tags');
        });

        Schema::table('meals', function (Blueprint $table): void {
            $table->json('safety_alert_tags')->nullable()->after('diet_tags');
            $table->boolean('sickle_cell_program_highlight')->default(false)->after('safety_alert_tags');
            $table->boolean('nutrition_aggregates_synced')->default(false)->after('sickle_cell_program_highlight');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropColumn('common_allergens');
        });

        Schema::table('meals', function (Blueprint $table): void {
            $table->dropColumn(['safety_alert_tags', 'sickle_cell_program_highlight', 'nutrition_aggregates_synced']);
        });
    }
};
