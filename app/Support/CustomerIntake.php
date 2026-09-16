<?php

namespace App\Support;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerContactPreference;
use App\Enums\CustomerDeliveryTime;
use App\Enums\CustomerPlanType;
use App\Enums\CustomerSex;
use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CustomerIntake
{
    /** @var list<string> */
    private const FOOD_FILTER_KEYS = [
        'dairy',
        'gluten',
        'eggs',
        'soy',
        'nightshades',
        'beans',
        'nuts',
        'spicy',
        'shellfish',
        'other',
        IngredientAllergenCatalog::PEANUTS,
        IngredientAllergenCatalog::TREE_NUTS,
        IngredientAllergenCatalog::WHEAT,
        IngredientAllergenCatalog::FISH,
        IngredientAllergenCatalog::SESAME,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $ignoreUserId, bool $requirePhone): array
    {
        $contactPreference = $requirePhone ? 'required' : 'nullable';
        $phone = $requirePhone ? 'required' : 'nullable';

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($ignoreUserId),
            ],
            'phone' => [$phone, 'string', 'max:32'],
            'contact_preference' => [$contactPreference, 'string', Rule::enum(CustomerContactPreference::class)],
            'date_of_birth' => [
                'nullable',
                'date',
                'after_or_equal:'.now()->subYears(100)->startOfYear()->toDateString(),
                'before_or_equal:'.now()->subYears(13)->toDateString(),
            ],
            'gender' => ['nullable', 'string', Rule::enum(CustomerSex::class)],
            'height_cm' => ['nullable', 'numeric', 'min:100', 'max:250'],
            'weight_kg' => ['nullable', 'numeric', 'min:40', 'max:200'],
            'target_weight_kg' => ['nullable', 'numeric', 'min:40', 'max:200'],
            'activity_level' => ['nullable', 'string', Rule::in(self::activityLevelValues())],
            'delivery_time' => ['nullable', 'string', Rule::enum(CustomerDeliveryTime::class)],
            'dislikes_and_allergies' => ['nullable', 'string', 'max:5000'],
            'diet_protocol' => ['nullable', 'string', Rule::enum(DietProtocol::class)],
            'plan_type' => ['nullable', 'string', Rule::enum(CustomerPlanType::class)],
            'plan_days' => ['nullable', 'integer', 'min:1', 'max:7'],
            'area' => ['nullable', 'string', 'max:120'],
            'block' => ['nullable', 'string', 'max:32'],
            'road' => ['nullable', 'string', 'max:64'],
            'house_number' => ['nullable', 'string', 'max:64'],
            'gate_flat_number' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'max:64'],
            'planned_start_date' => ['nullable', 'date'],
            'follow_instagram' => ['sometimes', 'boolean'],
            'customer_question' => ['nullable', 'string', 'max:5000'],
            'uncalculated_plan' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function prepare(array $input): array
    {
        $nullable = [
            'phone',
            'contact_preference',
            'date_of_birth',
            'gender',
            'height_cm',
            'weight_kg',
            'target_weight_kg',
            'activity_level',
            'delivery_time',
            'dislikes_and_allergies',
            'diet_protocol',
            'plan_type',
            'plan_days',
            'area',
            'block',
            'road',
            'house_number',
            'gate_flat_number',
            'country',
            'planned_start_date',
            'customer_question',
        ];

        foreach ($nullable as $key) {
            if (array_key_exists($key, $input) && $input[$key] === '') {
                $input[$key] = null;
            }
        }

        if (array_key_exists('follow_instagram', $input)) {
            $input['follow_instagram'] = filter_var($input['follow_instagram'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('uncalculated_plan', $input)) {
            $input['uncalculated_plan'] = filter_var($input['uncalculated_plan'], FILTER_VALIDATE_BOOLEAN);
        }

        return $input;
    }

    /**
     * @return array{form: array<string, mixed>, options: array<string, list<array{value: string, label: string}>>, uniqueCode: string, intakeSubmissionId: string}
     */
    public static function pageProps(CustomerProfile $profile): array
    {
        return [
            'form' => self::toFormArray($profile),
            'options' => self::optionLists(),
            'uniqueCode' => (string) ($profile->unique_code ?? ''),
            'intakeSubmissionId' => (string) ($profile->intake_submission_id ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toFormArray(CustomerProfile $profile): array
    {
        $profile->loadMissing(['user', 'craftPlans']);

        $userName = (string) ($profile->user?->name ?? '');
        [$splitFirst, $splitLast] = self::splitName($userName);

        $latestPlan = $profile->craftPlans
            ->sortByDesc('id')
            ->first();

        return [
            'first_name' => $profile->first_name ?: $splitFirst,
            'last_name' => $profile->last_name ?: $splitLast,
            'email' => (string) ($profile->user?->email ?? ''),
            'phone' => (string) ($profile->phone ?? ''),
            'contact_preference' => $profile->contact_preference?->value ?? '',
            'date_of_birth' => $profile->date_of_birth?->toDateString() ?? '',
            'gender' => $profile->sex?->value ?? (string) ($profile->gender ?? ''),
            'height_cm' => $profile->height_cm !== null ? (string) $profile->height_cm : '',
            'weight_kg' => $profile->weight_kg !== null ? (string) $profile->weight_kg : '',
            'target_weight_kg' => $profile->target_weight_kg !== null ? (string) $profile->target_weight_kg : '',
            'activity_level' => $profile->activity_level?->multiplierKey() ?? '',
            'delivery_time' => $profile->delivery_time?->value ?? '',
            'dislikes_and_allergies' => self::formatDislikesAndAllergies($profile),
            'diet_protocol' => (string) ($profile->diet_protocol ?? ''),
            'plan_type' => $profile->plan_type?->value ?? (string) ($latestPlan?->craft_key ?? ''),
            'plan_days' => $profile->plan_days !== null
                ? (string) $profile->plan_days
                : ($latestPlan?->week_duration !== null ? (string) $latestPlan->week_duration : ''),
            'area' => (string) ($profile->area ?? ''),
            'block' => (string) ($profile->block ?? ''),
            'road' => (string) ($profile->road ?? ''),
            'house_number' => (string) ($profile->house_number ?? ''),
            'gate_flat_number' => (string) ($profile->gate_flat_number ?? ''),
            'country' => (string) ($profile->country ?: 'Bahrain'),
            'planned_start_date' => $profile->planned_start_date?->toDateString() ?? '',
            'follow_instagram' => (bool) $profile->follow_instagram,
            'customer_question' => (string) ($profile->customer_question ?? ''),
            'uncalculated_plan' => (bool) $profile->uncalculated_plan,
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function optionLists(): array
    {
        return [
            'contactPreferences' => self::enumOptions(CustomerContactPreference::cases()),
            'genders' => self::enumOptions(CustomerSex::cases()),
            'activityLevels' => self::enumOptions(array_map(
                static fn (string $value): CustomerActivityLevel => CustomerActivityLevel::from($value),
                self::activityLevelValues(),
            )),
            'deliveryTimes' => self::enumOptions(CustomerDeliveryTime::cases()),
            'dietProtocols' => self::enumOptions(DietProtocol::cases()),
            'planTypes' => self::enumOptions(CustomerPlanType::cases()),
            'planDays' => array_map(
                static fn (int $days): array => [
                    'value' => (string) $days,
                    'label' => $days === 1 ? '1 day' : "{$days} days",
                ],
                [3, 5, 6, 7],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function apply(CustomerProfile $profile, array $validated, bool $assignSubmissionId = false): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile, $validated, $assignSubmissionId): void {
            if (array_key_exists('dislikes_and_allergies', $validated)) {
                $parsed = self::parseDislikesAndAllergies((string) ($validated['dislikes_and_allergies'] ?? ''));
                $profile->allergies = $parsed['allergies'];
                $profile->dislikes = $parsed['dislikes'];
            }

            $profile->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'contact_preference' => self::nullableEnum(CustomerContactPreference::class, $validated['contact_preference'] ?? null),
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'height_cm' => $validated['height_cm'] ?? null,
                'weight_kg' => $validated['weight_kg'] ?? null,
                'target_weight_kg' => $validated['target_weight_kg'] ?? null,
                'activity_level' => self::nullableEnum(CustomerActivityLevel::class, $validated['activity_level'] ?? null),
                'delivery_time' => self::nullableEnum(CustomerDeliveryTime::class, $validated['delivery_time'] ?? null),
                'diet_protocol' => $validated['diet_protocol'] ?? null,
                'plan_type' => self::nullableEnum(CustomerPlanType::class, $validated['plan_type'] ?? null),
                'plan_days' => $validated['plan_days'] ?? null,
                'area' => $validated['area'] ?? null,
                'block' => $validated['block'] ?? null,
                'road' => $validated['road'] ?? null,
                'house_number' => $validated['house_number'] ?? null,
                'gate_flat_number' => $validated['gate_flat_number'] ?? null,
                'country' => $validated['country'] ?? 'Bahrain',
                'planned_start_date' => $validated['planned_start_date'] ?? null,
                'follow_instagram' => (bool) ($validated['follow_instagram'] ?? false),
                'customer_question' => $validated['customer_question'] ?? null,
                'uncalculated_plan' => (bool) ($validated['uncalculated_plan'] ?? false),
            ]);

            if (! empty($validated['gender'])) {
                $sex = CustomerSex::from((string) $validated['gender']);
                $profile->sex = $sex;
                $profile->gender = $sex->value;
            }

            if (! empty($validated['date_of_birth'])) {
                $dateOfBirth = Carbon::parse((string) $validated['date_of_birth'])->startOfDay();
                $profile->date_of_birth = $dateOfBirth;
                $profile->age = (int) $dateOfBirth->diffInYears(now());
            }

            if ($assignSubmissionId && ($profile->intake_submission_id === null || $profile->intake_submission_id === '')) {
                $profile->intake_submission_id = (string) Str::uuid();
            }

            $profile->save();

            $user = $profile->user;
            if ($user !== null) {
                $user->fill([
                    'name' => self::joinName(
                        (string) $validated['first_name'],
                        (string) $validated['last_name'],
                    ),
                    'email' => (string) $validated['email'],
                ]);
                $user->save();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 16; $attempt++) {
            $code = 'MC-'.strtoupper(Str::random(8));

            if (! CustomerProfile::query()->where('unique_code', $code)->exists()) {
                return $code;
            }
        }

        return 'MC-'.strtoupper(Str::lower(Str::random(8)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitName(string $name): array
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $trimmed, 2) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    public static function joinName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }

    /**
     * @return list<string>
     */
    public static function activityLevelValues(): array
    {
        return [
            CustomerActivityLevel::Sedentary->value,
            CustomerActivityLevel::LightlyActive->value,
            CustomerActivityLevel::ModeratelyActive->value,
            CustomerActivityLevel::VeryActive->value,
        ];
    }

    /**
     * @return array{allergies: list<string>, dislikes: list<string>}
     */
    public static function parseDislikesAndAllergies(string $text): array
    {
        $items = collect(preg_split('/[\n,]+/', $text) ?: [])
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->unique()
            ->values();

        $allergyKeys = array_fill_keys(self::FOOD_FILTER_KEYS, true);

        $allergies = [];
        $dislikes = [];

        foreach ($items as $item) {
            $key = strtolower($item);
            if (isset($allergyKeys[$key])) {
                $allergies[] = $key;

                continue;
            }

            $dislikes[] = $item;
        }

        return [
            'allergies' => array_values(array_unique($allergies)),
            'dislikes' => array_values(array_unique($dislikes)),
        ];
    }

    public static function formatDislikesAndAllergies(CustomerProfile $profile): string
    {
        $parts = [];

        foreach (array_merge((array) $profile->allergies, (array) $profile->dislikes) as $item) {
            if (! is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed === '' || in_array($trimmed, $parts, true)) {
                continue;
            }

            $parts[] = $trimmed;
        }

        return implode(', ', $parts);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T|null
     */
    private static function nullableEnum(string $enumClass, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $enumClass::tryFrom($value);
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private static function enumOptions(array $cases): array
    {
        return array_map(
            static function (object $case): array {
                $label = method_exists($case, 'label') ? $case->label() : $case->name;

                return [
                    'value' => (string) $case->value,
                    'label' => (string) $label,
                ];
            },
            $cases,
        );
    }
}
