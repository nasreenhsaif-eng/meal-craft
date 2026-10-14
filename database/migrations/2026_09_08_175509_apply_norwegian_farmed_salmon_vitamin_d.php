<?php

use App\Services\NorwegianFarmedSalmonLibrarySync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(NorwegianFarmedSalmonLibrarySync::class)->apply();
    }

    public function down(): void
    {
        //
    }
};
