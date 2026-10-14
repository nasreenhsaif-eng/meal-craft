<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 32)->nullable()->after('last_name');
            $table->string('contact_preference', 16)->nullable()->after('phone');
            $table->string('delivery_time', 32)->nullable()->after('contact_preference');
            $table->string('plan_type', 32)->nullable()->after('diet_protocol');
            $table->unsignedTinyInteger('plan_days')->nullable()->after('plan_type');
            $table->string('area')->nullable()->after('dislikes');
            $table->string('block', 32)->nullable()->after('area');
            $table->string('road', 64)->nullable()->after('block');
            $table->string('house_number', 64)->nullable()->after('road');
            $table->string('gate_flat_number', 64)->nullable()->after('house_number');
            $table->string('country', 64)->nullable()->after('gate_flat_number');
            $table->date('planned_start_date')->nullable()->after('country');
            $table->boolean('follow_instagram')->default(false)->after('planned_start_date');
            $table->text('customer_question')->nullable()->after('follow_instagram');
            $table->boolean('uncalculated_plan')->default(false)->after('customer_question');
            $table->string('unique_code', 16)->nullable()->unique()->after('uncalculated_plan');
            $table->uuid('intake_submission_id')->nullable()->unique()->after('unique_code');
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'contact_preference',
                'delivery_time',
                'plan_type',
                'plan_days',
                'area',
                'block',
                'road',
                'house_number',
                'gate_flat_number',
                'country',
                'planned_start_date',
                'follow_instagram',
                'customer_question',
                'uncalculated_plan',
                'unique_code',
                'intake_submission_id',
            ]);
        });
    }
};
