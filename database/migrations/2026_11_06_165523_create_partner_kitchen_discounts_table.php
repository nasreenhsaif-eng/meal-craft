<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_kitchen_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('kind', 32)->default('fixed');
            $table->decimal('amount_bhd', 10, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_kitchen_discounts');
    }
};
