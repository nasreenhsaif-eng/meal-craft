<?php

namespace App\Support;

use BackedEnum;

final class EnumDropdownOptions
{
    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return list<array{value: string, label: string}>
     */
    public static function fromBackedEnum(string $enumClass): array
    {
        return array_values(array_map(
            static function (BackedEnum $case): array {
                if (method_exists($case, 'label')) {
                    return [
                        'value' => (string) $case->value,
                        'label' => (string) $case->label(),
                    ];
                }

                return [
                    'value' => (string) $case->value,
                    'label' => (string) __(str_replace('_', ' ', ucfirst((string) $case->value))),
                ];
            },
            $enumClass::cases(),
        ));
    }
}
