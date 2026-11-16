<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedCodes = [];

        foreach (DB::table('customer_profiles')->orderBy('id')->get(['id', 'user_id', 'first_name', 'last_name', 'unique_code']) as $row) {
            $updates = [];

            if ($row->first_name === null || $row->last_name === null) {
                $userName = (string) (DB::table('users')->where('id', $row->user_id)->value('name') ?? '');
                [$firstName, $lastName] = self::splitName($userName);

                if ($row->first_name === null) {
                    $updates['first_name'] = $firstName !== '' ? $firstName : null;
                }

                if ($row->last_name === null) {
                    $updates['last_name'] = $lastName !== '' ? $lastName : null;
                }
            }

            if ($row->unique_code === null || $row->unique_code === '') {
                $updates['unique_code'] = self::uniqueCode($usedCodes);
            } else {
                $usedCodes[$row->unique_code] = true;
            }

            if ($updates !== []) {
                DB::table('customer_profiles')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Identity backfill is not reversed; column drop lives on the schema migration.
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitName(string $name): array
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $trimmed, 2);

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    /**
     * @param  array<string, true>  $usedCodes
     */
    private static function uniqueCode(array &$usedCodes): string
    {
        do {
            $code = 'MC-'.strtoupper(Str::random(8));
        } while (isset($usedCodes[$code]));

        $usedCodes[$code] = true;

        return $code;
    }
};
