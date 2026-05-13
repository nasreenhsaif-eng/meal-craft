<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealCsvImportPendingRow extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'meal_name_key',
        'meal_name',
        'category',
        'ingredient_quantities',
        'instructions',
        'description_highlight',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
