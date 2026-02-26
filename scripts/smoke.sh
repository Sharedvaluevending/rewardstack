#!/usr/bin/env bash
set -euo pipefail

# Simple read-only smoke test script for the app.
# Usage:
#   BASE_URL=http://127.0.0.1 ./scripts/smoke.sh
# Optionally, pass additional endpoints via the ENDPOINTS env var (space-separated).

BASE_URL="${BASE_URL:-http://127.0.0.1}"

DEFAULT_ENDPOINTS=(
  "/health"
  "/"
  "/features"
  "/pricing"
  "/demo"
  "/privacy"
  "/terms"
)

# allow override/additional endpoints
if [ -n "${ENDPOINTS:-}" ]; then
  read -r -a EXTRA <<< "$ENDPOINTS"
else
  EXTRA=()
fi

ENDPOINTS=("${DEFAULT_ENDPOINTS[@]}" "${EXTRA[@]}")

echo "Running read-only smoke checks against $BASE_URL"
PASS=0
FAIL=0
echo
# Additional static asset sanity checks (manifest + fingerprinted assets)
MANIFEST_PATH="/app/public/build/manifest.json"
if [ -f "$MANIFEST_PATH" ]; then
  echo "Found manifest at public/build/manifest.json"
  # check for fingerprinted app asset under public/build/assets
  shopt -s nullglob
  app_files=(/app/public/build/assets/app-*.js)
  if [ ${#app_files[@]} -gt 0 ]; then
    echo "  fingerprinted app asset present: $(basename "${app_files[0]}")"
  else
    echo "  WARNING: no fingerprinted app asset found under public/build/assets/"
  fi
else
  echo "  NOTICE: public/build/manifest.json not found"
fi


for path in "${ENDPOINTS[@]}"; do
  url="${BASE_URL%/}${path}"
  printf "Checking %s ... " "$url"
  http_code=$(curl -s -o /tmp/smoke_resp --write-out "%{http_code}" --max-time 10 "$url" || echo "000")
  if [[ "$http_code" == "200" ]]; then
    # optionally check a simple content heuristic for non-health pages
    if [[ "$path" != "/health" ]]; then
      if grep -qiE "(<title>|<h1|features|pricing|demo|privacy|terms)" /tmp/smoke_resp >/dev/null 2>&1; then
        echo "OK (200 + content)"
        PASS=$((PASS+1))
      else
        echo "WARN (200 but content check failed)"
        PASS=$((PASS+1))
      fi
    else
      echo "OK (200)"
      PASS=$((PASS+1))
    fi
  else
    echo "FAIL (HTTP $http_code)"
    echo "---- response ----"
    sed -n '1,200p' /tmp/smoke_resp || true
    echo "------------------"
    FAIL=$((FAIL+1))
  fi
done

rm -f /tmp/smoke_resp

echo "Smoke summary: passed=$PASS failed=$FAIL"

if [ "$FAIL" -ne 0 ]; then
  echo "One or more smoke checks failed."
  exit 2
fi

echo "All smoke checks passed."
exit 0

#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-${APP_URL:-http://localhost}}"

echo "Health: $BASE_URL/health"
curl -fsS "$BASE_URL/health" | head -c 200 || exit 1

echo "Login page: $BASE_URL/login"
curl -fsS "$BASE_URL/login" >/dev/null

echo "OK"
