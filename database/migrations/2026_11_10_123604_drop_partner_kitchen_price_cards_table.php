<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('partner_kitchen_price_cards');
    }

    public function down(): void
    {
        // Price cards are replaced by plan_calorie_prices. Recreate is not restored.
    }
};
