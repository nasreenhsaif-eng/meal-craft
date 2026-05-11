<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->enum('diet_type', ['balanced', 'keto', 'intermittent_fasting'])->nullable()->after('diet_tags');
            $table->enum('cycle_phase', ['menstrual', 'follicular', 'ovulatory', 'luteal'])->nullable()->after('diet_type');
            $table->string('macro_focus')->nullable()->after('cycle_phase');
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn(['diet_type', 'cycle_phase', 'macro_focus']);
        });
    }
};
