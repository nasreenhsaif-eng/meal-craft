#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: not a git repository." >&2
    exit 1
fi

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
TIMESTAMP="$(date '+%Y-%m-%d %H:%M:%S')"
MESSAGE="Menu Menu Sync: ${TIMESTAMP}"

echo "Staging all changes..."
git add .

if git diff --cached --quiet; then
    echo "Nothing to commit — working tree is clean."
else
    echo "Committing: ${MESSAGE}"
    git commit -m "${MESSAGE}"
fi

echo "Pushing to origin/${BRANCH}..."
git push origin "${BRANCH}"

echo "Backup complete on branch: ${BRANCH}"
