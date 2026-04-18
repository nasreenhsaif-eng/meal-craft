<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('density', 12, 6)
                ->default(1.0)
                ->after('magnesium')
                ->comment('Mass per volume in g/ml for converting ml-based units to grams');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('density');
        });
    }
};
