<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FdcFoodIndex extends Model
{
    /** @use HasFactory<\Database\Factories\FdcFoodIndexFactory> */
    use HasFactory;

    protected $fillable = [
        'fdc_id',
        'data_type',
        'description',
        'ndb_number',
        'food_category',
        'publication_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fdc_id' => 'integer',
        ];
    }
}
