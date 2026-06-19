<?php

namespace App\Services;

use App\Support\UsdaNutrientMath;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fetches FoodData Central food detail payloads with optional disk cache.
 */
final class UsdaFoodDetailClient
{
    private const CACHE_DISK = 'local';

    private const CACHE_DIR = 'fdc-food-cache';

    public function __construct(
        private ?string $apiKey = null,
    ) {
        $this->apiKey = $apiKey ?? (string) config('services.usda.api_key', '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchFoodDetail(int $fdcId, bool $useCache = true): ?array
    {
        if ($fdcId <= 0) {
            return null;
        }

        $cachePath = self::CACHE_DIR.'/'.$fdcId.'.json';

        if ($useCache && Storage::disk(self::CACHE_DISK)->exists($cachePath)) {
            $decoded = json_decode(Storage::disk(self::CACHE_DISK)->get($cachePath), true);

            return is_array($decoded) ? $decoded : null;
        }

        $key = trim($this->apiKey) !== '' ? $this->apiKey : 'DEMO_KEY';

        try {
            $response = Http::timeout(30)
                ->retry(2, 500, throw: false)
                ->get('https://api.nal.usda.gov/fdc/v1/food/'.$fdcId, [
                    'api_key' => $key,
                ]);
        } catch (RequestException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        if ($useCache) {
            Storage::disk(self::CACHE_DISK)->put($cachePath, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        return $payload;
    }

    /**
     * @return array<string, float>
     */
    public function nutrientMapForFdcId(int $fdcId, bool $useCache = true): array
    {
        $detail = $this->fetchFoodDetail($fdcId, $useCache);

        if ($detail === null) {
            return [];
        }

        $nutrients = $detail['foodNutrients'] ?? null;

        if (! is_array($nutrients)) {
            return [];
        }

        return UsdaNutrientMath::mapByNutrientNumber($nutrients);
    }

    public function apiKeyConfigured(): bool
    {
        return trim((string) $this->apiKey) !== '';
    }

    public static function clearCache(): void
    {
        Storage::disk(self::CACHE_DISK)->deleteDirectory(self::CACHE_DIR);
    }

    /**
     * @throws RuntimeException
     */
    public function assertReachable(): void
    {
        $detail = $this->fetchFoodDetail(173414, false);

        if ($detail === null) {
            throw new RuntimeException('Unable to reach USDA FoodData Central API.');
        }
    }
}
