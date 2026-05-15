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
            $table->text('instructions')->nullable()->after('description');
            $table->string('short_description', 500)->nullable()->after('instructions');
        });

        if (Schema::hasColumn('meals', 'description')) {
            DB::table('meals')
                ->whereNull('instructions')
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->update(['instructions' => DB::raw('description')]);
        }

        if (Schema::hasColumn('meals', 'highlight')) {
            DB::table('meals')
                ->whereNull('short_description')
                ->whereNotNull('highlight')
                ->where('highlight', '!=', '')
                ->update(['short_description' => DB::raw('highlight')]);
        }
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->dropColumn(['instructions', 'short_description']);
        });
    }
};
