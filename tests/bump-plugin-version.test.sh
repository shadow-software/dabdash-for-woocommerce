#!/usr/bin/env bash
#
# Unit tests for .github/scripts/bump-plugin-version.sh. Plain bash + fixture
# directories — no framework dependency, runs the same on a laptop or in CI.
#
# Usage: tests/bump-plugin-version.test.sh
set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
script="${repo_root}/.github/scripts/bump-plugin-version.sh"
failures=0
tests_run=0

fixture_plugin_file() {
  local version="$1"
  cat <<EOF
<?php
/**
 * Plugin Name:       DabDash Sync for WooCommerce
 * Version:           ${version}
 * Requires PHP:      8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'DABDASH_WOO_VERSION', '${version}' );
EOF
}

fixture_readme_file() {
  local version="$1"
  cat <<EOF
=== DabDash Sync for WooCommerce ===
Stable tag: ${version}
Requires PHP: 8.1

== Description ==

Some description text.

== Changelog ==

= ${version} =
* Initial release.

== Upgrade Notice ==

= ${version} =
First public release.
EOF
}

new_case() {
  case_dir="$(mktemp -d)"
  trap_dirs+=("$case_dir")
}

trap_dirs=()
cleanup() {
  for d in "${trap_dirs[@]:-}"; do
    [ -n "$d" ] && rm -rf "$d"
  done
}
trap cleanup EXIT

assert_eq() {
  local desc="$1" expected="$2" actual="$3"
  tests_run=$((tests_run + 1))
  if [ "$expected" = "$actual" ]; then
    echo "ok - ${desc}"
  else
    echo "FAIL - ${desc}: expected '${expected}', got '${actual}'"
    failures=$((failures + 1))
  fi
}

assert_contains() {
  local desc="$1" file="$2" needle="$3"
  tests_run=$((tests_run + 1))
  if grep -qF "$needle" "$file"; then
    echo "ok - ${desc}"
  else
    echo "FAIL - ${desc}: '${needle}' not found in ${file}"
    failures=$((failures + 1))
  fi
}

assert_status() {
  local desc="$1" expected_status="$2" actual_status="$3"
  tests_run=$((tests_run + 1))
  if [ "$expected_status" = "$actual_status" ]; then
    echo "ok - ${desc}"
  else
    echo "FAIL - ${desc}: expected exit ${expected_status}, got ${actual_status}"
    failures=$((failures + 1))
  fi
}

# ── Happy path: 1.0.0 -> 1.0.1, all three locations agree, changelog added ──
new_case
fixture_plugin_file "1.0.0" > "$case_dir/dabdash-for-woocommerce.php"
fixture_readme_file "1.0.0" > "$case_dir/readme.txt"

out="$(cd "$case_dir" && bash "$script" "0.3.1")"
status=$?
assert_status "happy path exits 0" 0 "$status"
assert_eq "happy path prints the new version" "1.0.1" "$out"
assert_eq "plugin header bumped" "1.0.1" "$(grep -oE '^\s*\*\s*Version:\s*\S+' "$case_dir/dabdash-for-woocommerce.php" | awk '{print $NF}')"
assert_eq "DABDASH_WOO_VERSION const bumped" "1.0.1" "$(grep -oE "DABDASH_WOO_VERSION',\s*'[^']+'" "$case_dir/dabdash-for-woocommerce.php" | grep -oE "'[0-9][^']*'" | tr -d "'")"
assert_eq "readme Stable tag bumped" "1.0.1" "$(grep -oE '^Stable tag:\s*\S+' "$case_dir/readme.txt" | awk '{print $NF}')"
assert_contains "changelog heading for the new version" "$case_dir/readme.txt" "= 1.0.1 ="
assert_contains "changelog notes the SDK version" "$case_dir/readme.txt" "shadow-software/dabdash-php-sdk\` to 0.3.1"
assert_contains "old changelog entry for 1.0.0 is preserved" "$case_dir/readme.txt" "= 1.0.0 ="

# ── Sequential bumps: second run reads the already-bumped Stable tag ──
new_case
fixture_plugin_file "2.4.9" > "$case_dir/dabdash-for-woocommerce.php"
fixture_readme_file "2.4.9" > "$case_dir/readme.txt"

first="$(cd "$case_dir" && bash "$script" "1.0.0")"
second="$(cd "$case_dir" && bash "$script" "1.0.1")"
assert_eq "first sequential bump" "2.4.10" "$first"
assert_eq "second sequential bump continues from the first" "2.4.11" "$second"
assert_eq "readme reflects only the latest version" "2.4.11" "$(grep -oE '^Stable tag:\s*\S+' "$case_dir/readme.txt" | awk '{print $NF}')"
assert_contains "changelog has both new entries" "$case_dir/readme.txt" "= 2.4.10 ="
assert_contains "changelog has both new entries" "$case_dir/readme.txt" "= 2.4.11 ="

# ── Guard: a pre-release Stable tag must never be silently "bumped" ──
new_case
fixture_plugin_file "1.0.0-rc1" > "$case_dir/dabdash-for-woocommerce.php"
fixture_readme_file "1.0.0-rc1" > "$case_dir/readme.txt"

out="$(cd "$case_dir" && bash "$script" "0.3.1" 2>&1)"
status=$?
assert_status "pre-release Stable tag is refused" 1 "$status"
assert_eq "refused run leaves the plugin file untouched" "1.0.0-rc1" "$(grep -oE '^\s*\*\s*Version:\s*\S+' "$case_dir/dabdash-for-woocommerce.php" | awk '{print $NF}')"

# ── Guard: missing Stable tag line ──
new_case
fixture_plugin_file "1.0.0" > "$case_dir/dabdash-for-woocommerce.php"
printf '=== DabDash Sync for WooCommerce ===\nNo stable tag line here.\n' > "$case_dir/readme.txt"

(cd "$case_dir" && bash "$script" "0.3.1" > /dev/null 2>&1); status=$?
assert_status "missing Stable tag line is refused" 1 "$status"

# ── Guard: missing plugin file entirely ──
new_case
fixture_readme_file "1.0.0" > "$case_dir/readme.txt"
(cd "$case_dir" && bash "$script" "0.3.1" > /dev/null 2>&1); status=$?
assert_status "missing plugin file is refused" 1 "$status"

echo
echo "${tests_run} tests, ${failures} failures"
[ "$failures" -eq 0 ]
