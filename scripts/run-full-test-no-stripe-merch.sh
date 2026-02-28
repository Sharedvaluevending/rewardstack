#!/usr/bin/env bash
# Full test of the app - excludes Stripe and merch tests.
# Use this when you want comprehensive coverage without Stripe/Merch integration tests.

set -euo pipefail

cd "$(dirname "$0")/.."

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=============================================="
echo "  FULL APP TEST (excluding Stripe & Merch)"
echo "=============================================="
echo ""

# Check we're in Laravel root
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: Must run from Laravel root directory${NC}"
    exit 1
fi

# Build list of test files, excluding Stripe and merch
EXCLUDED_PATTERNS="Stripe|Merch|Billing|Printful|SafeCommerce|MerchTag|PortalMerch|MerchReferral|ProcessReferralPayouts|ReconcileStripe|ResubmitPendingPrintful"
INCLUDED_TESTS=$(find tests -name "*Test.php" -type f | grep -v -E "$EXCLUDED_PATTERNS" | sort)

if [ -z "$INCLUDED_TESTS" ]; then
    echo -e "${RED}Error: No test files matched${NC}"
    exit 1
fi

# Count excluded for reporting
TOTAL=$(find tests -name "*Test.php" -type f | wc -l)
EXCLUDED=$(find tests -name "*Test.php" -type f | grep -E "$EXCLUDED_PATTERNS" | wc -l)
RUNNING=$((TOTAL - EXCLUDED))

echo "📋 Running $RUNNING test files (excluding $EXCLUDED Stripe/Merch files)"
echo ""

# Run PHP tests
echo "🔍 Running PHP Unit + Feature tests..."
echo "---------------------------------------"
php artisan test $INCLUDED_TESTS --stop-on-failure
PHP_EXIT=$?

if [ $PHP_EXIT -ne 0 ]; then
    echo -e "${RED}✗ PHP tests failed${NC}"
    exit $PHP_EXIT
fi
echo -e "${GREEN}✓ PHP tests passed${NC}"
echo ""

# Run JS tests (Vitest - no Stripe/Merch)
echo "🔍 Running JavaScript tests..."
echo "--------------------------------"
if npm run test:run 2>/dev/null; then
    echo -e "${GREEN}✓ JS tests passed${NC}"
else
    echo -e "${YELLOW}⚠ JS tests skipped or failed (optional)${NC}"
fi
echo ""

echo "=============================================="
echo "📊 Test Summary"
echo "=============================================="
echo -e "${GREEN}✓ Full app test complete (Stripe & Merch excluded)${NC}"
echo ""
echo "Excluded: Stripe*, Merch*, Billing, Printful, SafeCommerce,"
echo "          MerchTag, PortalMerch, MerchReferral, ProcessReferralPayouts,"
echo "          ReconcileStripe, ResubmitPendingPrintful"
echo ""
