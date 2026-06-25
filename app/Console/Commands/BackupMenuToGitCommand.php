<?php

namespace App\Console\Commands;

use App\Services\MenuDevelopmentCsvSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BackupMenuToGitCommand extends Command
{
    protected $signature = 'menu:backup-git
                            {--message= : Custom commit message}
                            {--no-push : Export and commit only; do not push to origin}';

    protected $description = 'Export live menu library to CSV, commit changes, and push to the current git branch';

    public function handle(MenuDevelopmentCsvSync $menuDevelopmentCsvSync): int
    {
        if (! is_dir(base_path('.git'))) {
            $this->error('This project is not a git repository.');

            return self::FAILURE;
        }

        $counts = $menuDevelopmentCsvSync->syncAllFromDatabase();

        $this->info(sprintf('Exported %d ingredient row(s) and %d meal row(s).', $counts['ingredients'], $counts['meals']));

        $add = Process::path(base_path())->run('git add database/data/menu/meals.csv database/data/menu/ingredients.csv public/images/meals');
        if (! $add->successful()) {
            $this->error(trim($add->errorOutput() ?: $add->output()));

            return self::FAILURE;
        }

        $diff = Process::path(base_path())->run('git diff --cached --quiet');
        if ($diff->successful()) {
            $this->info('Nothing to commit — menu CSV files already match the database.');

            return self::SUCCESS;
        }

        $message = $this->option('message')
            ?: 'Menu Sync: '.now()->format('Y-m-d H:i:s');

        $commit = Process::path(base_path())->run(['git', 'commit', '-m', $message]);
        if (! $commit->successful()) {
            $this->error(trim($commit->errorOutput() ?: $commit->output()));

            return self::FAILURE;
        }

        $this->info("Committed: {$message}");

        if ($this->option('no-push')) {
            return self::SUCCESS;
        }

        $branch = trim(Process::path(base_path())->run('git rev-parse --abbrev-ref HEAD')->output());
        $push = Process::path(base_path())->run(['git', 'push', 'origin', $branch]);
        if (! $push->successful()) {
            $this->error(trim($push->errorOutput() ?: $push->output()));

            return self::FAILURE;
        }

        $this->info("Pushed to origin/{$branch}.");

        return self::SUCCESS;
    }
}
