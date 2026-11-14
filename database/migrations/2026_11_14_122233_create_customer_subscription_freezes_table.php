<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscription_freezes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained()->cascadeOnDelete();
            $table->date('frozen_on');
            $table->timestamps();

            $table->unique(['customer_subscription_id', 'frozen_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscription_freezes');
    }
};
