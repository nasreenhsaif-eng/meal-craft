<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AuditIngredientLibraryCommand extends Command
{
    protected $signature = 'ingredients:audit-library
                            {--fdc : Compare stored values against USDA FDC payloads (slow; requires network)}';

    protected $description = 'Audit ingredient library nutrition: base recipe rollups and optional FDC cross-check';

    public function handle(): int
    {
        $script = base_path('scripts/audit-ingredient-library.php');

        if (! is_file($script)) {
            $this->error('Audit script not found: '.$script);

            return self::FAILURE;
        }

        $env = $this->option('fdc') ? [] : ['AUDIT_SKIP_FDC' => '1'];

        $process = new Process([PHP_BINARY, $script], base_path(), $env);
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput()) ?: 'Ingredient library audit failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
