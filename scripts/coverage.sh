#!/usr/bin/env bash
# Run tests with code coverage (requires PCOV or Xdebug on the host).
# Use this to track progress toward 80% total app coverage.
# Safe workflow: run full test suite without coverage first, then run this.
#
# IMPORTANT: This runs the FULL suite. If you run "php artisan test --coverage"
# with a filter or single file, you overwrite build/coverage with a partial
# report and will see a fake low total (e.g. 0.76%). Use this script or
# ./scripts/coverage-full.sh for the real %.

set -euo pipefail

cd "$(dirname "$0")/.."

echo "=== Step 1: Run tests without coverage (ensure nothing is broken) ==="
php artisan test

echo ""
echo "=== Step 2: Run tests with coverage ==="
mkdir -p build/coverage
php artisan test --coverage

echo ""
echo "=== Coverage report ==="
echo "HTML report: build/coverage/index.html (open in browser for per-file breakdown)"
echo "Target: 80% total app coverage. Add tests in small batches and re-run this script."
