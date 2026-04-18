<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_ingredient', function (Blueprint $table) {
            $table->decimal('amount', 14, 4)->default(0)->after('ingredient_id');
            $table->string('unit', 16)->default('g')->after('amount');
        });

        DB::table('recipe_ingredient')->update([
            'amount' => DB::raw('amount_grams'),
            'unit' => 'g',
        ]);
    }

    public function down(): void
    {
        Schema::table('recipe_ingredient', function (Blueprint $table) {
            $table->dropColumn(['amount', 'unit']);
        });
    }
};
