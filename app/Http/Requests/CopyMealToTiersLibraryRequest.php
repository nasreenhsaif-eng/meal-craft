<?php

namespace App\Http\Requests;

use App\Enums\MealLibraryKey;
use App\Models\Meal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CopyMealToTiersLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meal_id' => ['required', 'integer', 'exists:meals,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $id = (int) $this->input('meal_id');
                $meal = Meal::query()->find($id);

                if ($meal === null || $meal->library_key === MealLibraryKey::Tiers) {
                    $validator->errors()->add('meal_id', __('Choose a meal from the current Meal Library.'));
                }
            },
        ];
    }
}
