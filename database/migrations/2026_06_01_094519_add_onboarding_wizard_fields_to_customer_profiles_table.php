<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_profiles', 'gender')) {
                $table->string('gender', 16)->nullable()->after('sex');
            }

            if (! Schema::hasColumn('customer_profiles', 'period_tracking_data')) {
                $table->json('period_tracking_data')->nullable()->after('average_cycle_length');
            }

            if (! Schema::hasColumn('customer_profiles', 'birthdate')) {
                $table->date('birthdate')->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('customer_profiles', 'food_filters')) {
                $table->json('food_filters')->nullable()->after('allergies');
            }
        });

        foreach (DB::table('customer_profiles')->whereNotNull('sex')->whereNull('gender')->get(['id', 'sex']) as $row) {
            DB::table('customer_profiles')->where('id', $row->id)->update(['gender' => $row->sex]);
        }

        foreach (DB::table('customer_profiles')->whereNotNull('date_of_birth')->whereNull('birthdate')->get(['id', 'date_of_birth']) as $row) {
            DB::table('customer_profiles')->where('id', $row->id)->update(['birthdate' => $row->date_of_birth]);
        }

        foreach (DB::table('customer_profiles')->whereNotNull('allergies')->whereNull('food_filters')->get(['id', 'allergies']) as $row) {
            DB::table('customer_profiles')->where('id', $row->id)->update(['food_filters' => $row->allergies]);
        }

        $activityMap = [
            'light' => 'lightly_active',
            'moderate' => 'lightly_active',
            'active' => 'moderately_active',
        ];

        foreach ($activityMap as $from => $to) {
            DB::table('customer_profiles')
                ->where('activity_level', $from)
                ->update(['activity_level' => $to]);
        }

        DB::table('customer_profiles')
            ->where('diet_protocol', 'sickle_cell')
            ->update(['diet_protocol' => 'sickle_cell_warrior']);
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profiles', 'gender')) {
                $table->dropColumn('gender');
            }

            if (Schema::hasColumn('customer_profiles', 'period_tracking_data')) {
                $table->dropColumn('period_tracking_data');
            }

            if (Schema::hasColumn('customer_profiles', 'birthdate')) {
                $table->dropColumn('birthdate');
            }

            if (Schema::hasColumn('customer_profiles', 'food_filters')) {
                $table->dropColumn('food_filters');
            }
        });
    }
};
