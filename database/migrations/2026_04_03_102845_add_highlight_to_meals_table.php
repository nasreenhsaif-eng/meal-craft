<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->text('highlight')->nullable()->after('description');
            $table->decimal('health_score', 10, 2)->nullable()->after('total_vitamin_k');
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn(['highlight', 'health_score']);
        });
    }
};
