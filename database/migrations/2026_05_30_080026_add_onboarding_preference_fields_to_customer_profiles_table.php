<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->decimal('target_weight_kg', 6, 2)->nullable()->after('weight_kg');
            $table->string('goal', 32)->nullable()->after('activity_level');
            $table->string('diet_type', 32)->nullable()->after('goal');
            $table->json('allergies')->nullable()->after('fat_percentage');
            $table->json('dislikes')->nullable()->after('allergies');
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'target_weight_kg',
                'goal',
                'diet_type',
                'allergies',
                'dislikes',
            ]);
        });
    }
};
