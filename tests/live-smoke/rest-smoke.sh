#!/usr/bin/env bash
# Thin HTTP smoke — runs from CI against the public digest site (no SSH).
# Uses WP REST + optional application password for authenticated probes.
set -euo pipefail

BASE="${SMOKE_SITE_URL:?SMOKE_SITE_URL required — e.g. https://cannabisdigest.net}"
SLUG="${SMOKE_PLUGIN_SLUG:-dabdash-for-woocommerce}"

echo "── REST smoke: ${BASE} ──"

curl -fsS --max-time 30 "${BASE}/wp-json/" >/dev/null
echo "✓ wp-json root"

curl -fsS --max-time 30 "${BASE}/wp-json/wp/v2/types" >/dev/null
echo "✓ wp/v2/types"

# WooCommerce exposes system_status when WC is active.
if curl -fsS --max-time 30 "${BASE}/wp-json/wc/v3" >/dev/null 2>&1; then
	echo "✓ wc/v3 namespace"
else
	echo "⚠ wc/v3 not reachable (WooCommerce may not be installed yet)"
fi

if [[ -n "${SMOKE_WP_APP_PASSWORD:-}" && -n "${SMOKE_WP_USER:-}" ]]; then
	# Authenticated: confirm plugin is installed and active.
	plugins="$(curl -fsS --max-time 30 -u "${SMOKE_WP_USER}:${SMOKE_WP_APP_PASSWORD}" \
		"${BASE}/wp-json/wp/v2/plugins?status=active")"
	# wp/v2/plugins JSON escapes slashes in the plugin id string.
	echo "$plugins" | tr -d '\\' | grep -q "${SLUG}/${SLUG}" \
		|| { echo "✗ plugin ${SLUG} not active" >&2; exit 1; }
	echo "✓ plugin active (REST)"
fi

echo "REST smoke passed."
