<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('total_b6', 14, 4)->default(0)->after('total_fat');
            $table->decimal('total_folate', 14, 4)->default(0)->after('total_b6');
            $table->decimal('total_b12', 14, 4)->default(0)->after('total_folate');
            $table->decimal('total_iron', 14, 4)->default(0)->after('total_b12');
            $table->decimal('total_magnesium', 14, 4)->default(0)->after('total_iron');
            $table->decimal('total_fiber', 14, 4)->default(0)->after('total_magnesium');
            $table->decimal('total_sugar', 14, 4)->default(0)->after('total_fiber');
            $table->decimal('total_calcium', 14, 4)->default(0)->after('total_sugar');
            $table->decimal('total_potassium', 14, 4)->default(0)->after('total_calcium');
            $table->decimal('total_sodium', 14, 4)->default(0)->after('total_potassium');
            $table->decimal('total_zinc', 14, 4)->default(0)->after('total_sodium');
            $table->decimal('total_vitamin_c', 14, 4)->default(0)->after('total_zinc');
            $table->decimal('total_vitamin_a', 14, 4)->default(0)->after('total_vitamin_c');
            $table->decimal('total_vitamin_e', 14, 4)->default(0)->after('total_vitamin_a');
            $table->decimal('total_vitamin_d', 14, 4)->default(0)->after('total_vitamin_e');
            $table->decimal('total_vitamin_k', 14, 4)->default(0)->after('total_vitamin_d');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn([
                'total_b6',
                'total_folate',
                'total_b12',
                'total_iron',
                'total_magnesium',
                'total_fiber',
                'total_sugar',
                'total_calcium',
                'total_potassium',
                'total_sodium',
                'total_zinc',
                'total_vitamin_c',
                'total_vitamin_a',
                'total_vitamin_e',
                'total_vitamin_d',
                'total_vitamin_k',
            ]);
        });
    }
};
