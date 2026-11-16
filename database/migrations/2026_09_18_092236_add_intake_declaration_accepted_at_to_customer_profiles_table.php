<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->timestamp('intake_declaration_accepted_at')->nullable()->after('intake_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->dropColumn('intake_declaration_accepted_at');
        });
    }
};
