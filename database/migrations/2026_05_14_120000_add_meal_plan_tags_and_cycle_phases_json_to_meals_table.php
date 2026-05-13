<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->json('meal_plan_tags')->nullable()->after('meal_plan_tag');
            $table->json('cycle_phases')->nullable()->after('cycle_phase');
        });

        if (Schema::hasColumn('meals', 'meal_plan_tag') && Schema::hasColumn('meals', 'meal_plan_tags')) {
            DB::table('meals')->select(['id', 'meal_plan_tag'])->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $tag = trim((string) ($row->meal_plan_tag ?? ''));
                    if ($tag === '') {
                        continue;
                    }
                    DB::table('meals')->where('id', $row->id)->update([
                        'meal_plan_tags' => json_encode([$tag]),
                    ]);
                }
            });
        }

        if (Schema::hasColumn('meals', 'cycle_phase') && Schema::hasColumn('meals', 'cycle_phases')) {
            DB::table('meals')->select(['id', 'cycle_phase'])->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $phase = trim((string) ($row->cycle_phase ?? ''));
                    if ($phase === '') {
                        continue;
                    }
                    DB::table('meals')->where('id', $row->id)->update([
                        'cycle_phases' => json_encode([$phase]),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->dropColumn(['meal_plan_tags', 'cycle_phases']);
        });
    }
};
