<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_profiles')
            ->where('diet_protocol', 'ketogenic')
            ->update(['diet_protocol' => 'ketobiotic']);
    }

    public function down(): void
    {
        DB::table('customer_profiles')
            ->where('diet_protocol', 'ketobiotic')
            ->update(['diet_protocol' => 'ketogenic']);
    }
};
