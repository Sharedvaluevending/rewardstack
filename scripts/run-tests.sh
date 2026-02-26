#!/bin/bash

# Test Runner Script for Production Launch
# This script runs all tests with proper configuration

set -e

echo "🧪 Running Test Suite for Production Launch"
echo "=============================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: Must run from Laravel root directory${NC}"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION_ID;")
if [ "$PHP_VERSION" -lt 80100 ]; then
    echo -e "${RED}Error: PHP 8.1+ required${NC}"
    exit 1
fi

echo "📋 Pre-flight Checks"
echo "-------------------"
echo "✓ PHP Version: $(php -v | head -n 1)"
echo "✓ Laravel: $(php artisan --version)"
echo ""

# Run tests with different configurations
echo "🔍 Running Unit Tests..."
echo "------------------------"
php artisan test --testsuite=Unit --stop-on-failure
UNIT_EXIT=$?

if [ $UNIT_EXIT -ne 0 ]; then
    echo -e "${RED}✗ Unit tests failed${NC}"
    exit $UNIT_EXIT
fi
echo -e "${GREEN}✓ Unit tests passed${NC}"
echo ""

echo "🔍 Running Feature Tests..."
echo "---------------------------"
php artisan test --testsuite=Feature --stop-on-failure
FEATURE_EXIT=$?

if [ $FEATURE_EXIT -ne 0 ]; then
    echo -e "${RED}✗ Feature tests failed${NC}"
    exit $FEATURE_EXIT
fi
echo -e "${GREEN}✓ Feature tests passed${NC}"
echo ""

# Optional: Run with coverage (requires Xdebug or PCOV)
if command -v phpdbg &> /dev/null || php -m | grep -q xdebug || php -m | grep -q pcov; then
    echo "📊 Generating Test Coverage Report..."
    echo "-------------------------------------"
    php artisan test --coverage --min=70
    COVERAGE_EXIT=$?
    
    if [ $COVERAGE_EXIT -ne 0 ]; then
        echo -e "${YELLOW}⚠ Coverage below threshold${NC}"
    else
        echo -e "${GREEN}✓ Coverage threshold met${NC}"
    fi
    echo ""
fi

# Summary
echo "=============================================="
echo "📊 Test Summary"
echo "=============================================="
echo -e "${GREEN}✓ All tests completed${NC}"
echo ""

# Check for critical test files
echo "🔍 Checking Critical Test Files..."
echo "-----------------------------------"
CRITICAL_TESTS=(
    "tests/Unit/Services/GameServiceTest.php"
    "tests/Unit/Services/PrizeServiceTest.php"
    "tests/Feature/StripeWebhookTest.php"
    "tests/Feature/GamePlayFlowTest.php"
)

MISSING=0
for test in "${CRITICAL_TESTS[@]}"; do
    if [ -f "$test" ]; then
        echo -e "${GREEN}✓${NC} $test"
    else
        echo -e "${RED}✗${NC} $test (MISSING)"
        MISSING=1
    fi
done

if [ $MISSING -eq 1 ]; then
    echo ""
    echo -e "${YELLOW}⚠ Some critical test files are missing${NC}"
fi

echo ""
echo "✅ Test run complete!"
echo ""
echo "Next steps:"
echo "  1. Review any failing tests"
echo "  2. Check test coverage report"
echo "  3. Run manual testing on staging"
echo "  4. Perform load testing"
echo ""

