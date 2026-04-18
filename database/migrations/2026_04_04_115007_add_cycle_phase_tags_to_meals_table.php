<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->json('cycle_phase_tags')->nullable()->after('health_score');
            $table->boolean('cycle_phase_tags_manual')->default(false)->after('cycle_phase_tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn(['cycle_phase_tags', 'cycle_phase_tags_manual']);
        });
    }
};
