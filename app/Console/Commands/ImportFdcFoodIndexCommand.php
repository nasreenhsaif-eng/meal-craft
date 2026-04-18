<?php

namespace App\Console\Commands;

use App\Models\FdcFoodIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportFdcFoodIndexCommand extends Command
{
    private const UPSERT_CHUNK_SIZE = 250;

    protected $signature = 'fdc:import
                            {path : Absolute or relative path to a UTF-8 CSV file}
                            {--truncate : Delete all rows in fdc_food_indices before importing}';

    protected $description = 'Import FoodData Central-style foods from CSV (fdc_id, description, data_type; optional food_category, publication_date, ndb_number). For use with the Ingredients local USDA browse.';

    public function handle(): int
    {
        set_time_limit(0);

        $rawPath = (string) $this->argument('path');
        $path = File::exists($rawPath) ? $rawPath : base_path($rawPath);

        if (! File::exists($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$rawPath}");

            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            FdcFoodIndex::query()->delete();
            $this->info('Truncated fdc_food_indices.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->error('Could not open CSV.');

            return self::FAILURE;
        }

        $headerLine = fgets($handle);

        if ($headerLine === false) {
            fclose($handle);
            $this->error('CSV is empty.');

            return self::FAILURE;
        }

        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine) ?? $headerLine;
        $header = str_getcsv($headerLine);

        if ($header === false || $header === []) {
            fclose($handle);
            $this->error('Invalid CSV header.');

            return self::FAILURE;
        }

        $columnIndex = [];

        foreach ($header as $i => $name) {
            $key = strtolower(trim((string) $name));
            $key = str_replace([' ', '-'], '_', $key);
            $columnIndex[$key] = $i;
        }

        $required = ['fdc_id', 'description', 'data_type'];
        $aliases = [
            'fdc_id' => ['fdc_id', 'fdcid', 'fdc_id_', 'id'],
            'description' => ['description', 'food_description'],
            'data_type' => ['data_type', 'datatype', 'type'],
            'ndb_number' => ['ndb_number', 'ndbnumber', 'ndb'],
            'food_category' => ['food_category', 'category', 'sr_foundation_food_category'],
            'publication_date' => ['publication_date', 'publicationdate', 'most_recent_acquisition_date', 'acquisition_date'],
        ];

        $resolved = [];

        foreach ($aliases as $canonical => $keys) {
            $resolved[$canonical] = null;

            foreach ($keys as $k) {
                if (array_key_exists($k, $columnIndex)) {
                    $resolved[$canonical] = $columnIndex[$k];

                    break;
                }
            }
        }

        foreach ($required as $field) {
            if ($resolved[$field] === null) {
                fclose($handle);
                $this->error("Missing required column for {$field}. Header was: ".implode(', ', $header));

                return self::FAILURE;
            }
        }

        $imported = 0;
        $skipped = 0;
        $buffer = [];
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = str_getcsv($line);

            if ($row === false || $row === []) {
                $skipped++;

                continue;
            }

            $fdcId = $this->intCell($row, $resolved['fdc_id']);

            if ($fdcId === null) {
                $skipped++;

                continue;
            }

            $description = $this->stringCell($row, $resolved['description']);

            if ($description === '') {
                $skipped++;

                continue;
            }

            $dataType = $this->stringCell($row, $resolved['data_type']);

            if ($dataType === '') {
                $skipped++;

                continue;
            }

            $buffer[] = [
                'fdc_id' => $fdcId,
                'description' => $description,
                'data_type' => $dataType,
                'ndb_number' => $resolved['ndb_number'] !== null ? $this->nullableStringCell($row, $resolved['ndb_number']) : null,
                'food_category' => $resolved['food_category'] !== null ? $this->nullableStringCell($row, $resolved['food_category']) : null,
                'publication_date' => $resolved['publication_date'] !== null ? $this->nullableStringCell($row, $resolved['publication_date']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $imported++;

            if (count($buffer) >= self::UPSERT_CHUNK_SIZE) {
                FdcFoodIndex::upsert(
                    $buffer,
                    ['fdc_id'],
                    ['description', 'data_type', 'ndb_number', 'food_category', 'publication_date', 'updated_at'],
                );
                $buffer = [];
                $now = now();
            }
        }

        if ($buffer !== []) {
            FdcFoodIndex::upsert(
                $buffer,
                ['fdc_id'],
                ['description', 'data_type', 'ndb_number', 'food_category', 'publication_date', 'updated_at'],
            );
        }

        fclose($handle);

        $this->info("Imported or updated {$imported} rows (skipped {$skipped}).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function intCell(array $row, int $index): ?int
    {
        $v = $row[$index] ?? null;

        if ($v === null || $v === '') {
            return null;
        }

        if (! is_numeric($v)) {
            return null;
        }

        return (int) $v;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function stringCell(array $row, int $index): string
    {
        $v = $row[$index] ?? '';

        return trim((string) $v);
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function nullableStringCell(array $row, int $index): ?string
    {
        $s = $this->stringCell($row, $index);

        return $s === '' ? null : $s;
    }
}
