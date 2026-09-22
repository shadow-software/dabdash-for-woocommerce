#!/usr/bin/env bash
# Build the lean installable plugin tree (same layout as release.yml / wp.org ZIP).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SLUG="${PLUGIN_SLUG:-dabdash-for-woocommerce}"
OUT="${1:-${RUNNER_TEMP:-/tmp}/${SLUG}}"

mkdir -p "$OUT"
rsync -a --exclude-from="${ROOT}/.distignore" "${ROOT}/" "$OUT/"
bash "${ROOT}/.github/prune-vendor-dev.sh" "$OUT"
test -f "$OUT/vendor/autoload.php"
test -f "$OUT/dabdash-for-woocommerce.php"
echo "$OUT"
