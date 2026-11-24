<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_craft_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->date('starts_on');
            $table->date('expires_on');
            $table->unsignedSmallInteger('delivery_days_count')->default(0);
            $table->string('fulfillment_choice')->nullable();
            $table->string('craft_key')->nullable();
            $table->string('meal_plan_name')->nullable();
            $table->decimal('amount_bhd', 10, 3)->nullable();
            $table->timestamps();

            $table->index(['customer_profile_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
