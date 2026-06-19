<?php

use App\Models\Ingredient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('meals', 'total_vitamin_k') && ! Schema::hasColumn('meals', 'total_vitamin_k2')) {
            Schema::table('meals', function (Blueprint $table): void {
                $table->renameColumn('total_vitamin_k', 'total_vitamin_k2');
            });
        }

        Ingredient::query()->lazyById()->each(function (Ingredient $ingredient): void {
            $micros = $ingredient->micronutrients;

            if (! is_array($micros)) {
                return;
            }

            $changed = false;

            if (array_key_exists('vitamin_k', $micros) && ! array_key_exists('vitamin_k2', $micros)) {
                unset($micros['vitamin_k']);
                $micros['vitamin_k2'] = 0.0;
                $changed = true;
            }

            if (array_key_exists('vitamin_k', $micros)) {
                unset($micros['vitamin_k']);
                $changed = true;
            }

            if ($changed) {
                $ingredient->forceFill(['micronutrients' => $micros])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('meals', 'total_vitamin_k2') && ! Schema::hasColumn('meals', 'total_vitamin_k')) {
            Schema::table('meals', function (Blueprint $table): void {
                $table->renameColumn('total_vitamin_k2', 'total_vitamin_k');
            });
        }

        Ingredient::query()->lazyById()->each(function (Ingredient $ingredient): void {
            $micros = $ingredient->micronutrients;

            if (! is_array($micros) || ! array_key_exists('vitamin_k2', $micros)) {
                return;
            }

            $micros['vitamin_k'] = $micros['vitamin_k2'];
            unset($micros['vitamin_k2']);

            $ingredient->forceFill(['micronutrients' => $micros])->saveQuietly();
        });
    }
};
