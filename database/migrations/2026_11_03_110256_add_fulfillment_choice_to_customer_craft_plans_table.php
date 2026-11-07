<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_craft_plans', function (Blueprint $table) {
            $table->string('fulfillment_choice', 32)->nullable()->after('craft_key');
        });
    }

    public function down(): void
    {
        Schema::table('customer_craft_plans', function (Blueprint $table) {
            $table->dropColumn('fulfillment_choice');
        });
    }
};
