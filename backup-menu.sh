#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: not a git repository." >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "Error: php is not available on PATH." >&2
    exit 1
fi

echo "Exporting live menu database to CSV and pushing to git..."
php artisan menu:backup-git

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
echo "Backup complete on branch: ${BRANCH}"
