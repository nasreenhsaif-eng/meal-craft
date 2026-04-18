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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->unsignedBigInteger('fdc_id')->nullable()->unique()->after('name');
            $table->text('functional_tip')->nullable()->after('fat');
            $table->boolean('is_verified')->default(false)->after('functional_tip');
            $table->json('fdc_key_nutrients')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['fdc_id', 'functional_tip', 'is_verified', 'fdc_key_nutrients']);
        });
    }
};
