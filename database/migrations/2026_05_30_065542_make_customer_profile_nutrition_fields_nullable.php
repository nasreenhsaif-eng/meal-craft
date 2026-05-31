<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->unsignedInteger('daily_calorie_target')->nullable()->change();
            $table->decimal('protein_percentage', 5, 2)->nullable()->change();
            $table->decimal('carb_percentage', 5, 2)->nullable()->change();
            $table->decimal('fat_percentage', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->unsignedInteger('daily_calorie_target')->nullable(false)->change();
            $table->decimal('protein_percentage', 5, 2)->nullable(false)->change();
            $table->decimal('carb_percentage', 5, 2)->nullable(false)->change();
            $table->decimal('fat_percentage', 5, 2)->nullable(false)->change();
        });
    }
};
