<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_kitchen_price_cards', function (Blueprint $table) {
            $table->decimal('discount_bhd', 10, 3)->default(0)->after('amount_bhd');
        });
    }

    public function down(): void
    {
        Schema::table('partner_kitchen_price_cards', function (Blueprint $table) {
            $table->dropColumn('discount_bhd');
        });
    }
};
