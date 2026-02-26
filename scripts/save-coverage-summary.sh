#!/usr/bin/env bash
# After running a FULL coverage run (php artisan test --coverage, no filter),
# run this to save what's covered and what's not to docs/coverage-last/.
# Commit that folder so we have a record and don't retest the same things.

set -euo pipefail
cd "$(dirname "$0")/.."
php scripts/save-coverage-summary.php
echo ""
echo "Next: commit docs/coverage-last/ so this record is saved."
