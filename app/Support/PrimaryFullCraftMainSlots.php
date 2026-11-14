<?php

namespace App\Support;

use App\Enums\DietProtocol;
use App\Models\CustomerProfile;

/**
 * Default primary main carousel slots used for reconciliation when the customer has not picked yet.
 *
 * Balanced: slots 1 + 3 (chicken plate + fish).
 * Nutrient Density (TBD Weekly Protocol): slots 1 + 5 (chicken plate + liver) for max density.
 */
final class PrimaryFullCraftMainSlots
{
    /** @var list<int> */
    public const BALANCED = [1, 3];

    /** @var list<int> */
    public const NUTRIENT_DENSE = [1, 5];

    /**
     * @return list<int>
     */
    public static function forProtocol(?DietProtocol $protocol): array
    {
        return $protocol === DietProtocol::NutrientDense
            ? self::NUTRIENT_DENSE
            : self::BALANCED;
    }

    /**
     * @return list<int>
     */
    public static function forProfile(CustomerProfile $profile): array
    {
        return self::forProtocol(DietProtocol::tryFromStored($profile->diet_protocol));
    }

    public static function isPrimarySlot(int $slotIndex, CustomerProfile $profile): bool
    {
        return in_array($slotIndex, self::forProfile($profile), true);
    }
}
