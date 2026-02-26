#!/usr/bin/env bash
# Run the FULL test suite with coverage to get the REAL total %.
# Partial runs (e.g. with a file or filter) overwrite build/coverage and show a fake low %.
# Run this script when you want to know or improve total app coverage.

set -euo pipefail

cd "$(dirname "$0")/.."

echo "=============================================="
echo "  FULL COVERAGE RUN (all Unit + all Feature)"
echo "  Do NOT use --filter or a single file."
echo "  This may take 10-15+ minutes."
echo "=============================================="
echo ""

mkdir -p build/coverage
php artisan test --coverage

echo ""
echo "=============================================="
echo "  Done. Open build/coverage/index.html for your real %."
echo "  IMPORTANT: Run ./scripts/save-coverage-summary.sh"
echo "  then COMMIT docs/coverage-last/ so we have a record of"
echo "  what's covered and what's not (no more guessing)."
echo "=============================================="
