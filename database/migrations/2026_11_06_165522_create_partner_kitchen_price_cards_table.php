<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_kitchen_price_cards', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32);
            $table->unsignedSmallInteger('calorie_row')->default(0);
            $table->unsignedTinyInteger('billed_days');
            $table->decimal('amount_bhd', 10, 3);
            $table->timestamps();

            $table->unique(['scope', 'calorie_row', 'billed_days'], 'pk_price_cards_scope_cal_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_kitchen_price_cards');
    }
};
