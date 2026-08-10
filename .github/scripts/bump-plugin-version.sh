#!/usr/bin/env bash
#
# Bumps this plugin's patch version everywhere it must agree (the plugin
# header, the DABDASH_WOO_VERSION constant, and readme.txt's Stable tag —
# release.yml's own tag-verification step enforces all three match), and
# adds a changelog entry noting the SDK bump that triggered this release.
#
# Pure and side-effect-scoped to the two files it edits in the current
# working directory — no CI-specific behaviour, no network calls — so it can
# be unit tested directly. See tests/bump-plugin-version.test.sh.
#
# Usage: bump-plugin-version.sh <sdk-version>
# Prints the new plugin version to stdout on success.
# Exits non-zero with a message on stderr if readme.txt's Stable tag is not
# a plain X.Y.Z release version (the only shape a Stable tag may ever take).
set -euo pipefail

sdk_version="${1:?usage: bump-plugin-version.sh <sdk-version>}"
plugin_file="dabdash-for-woocommerce.php"
readme_file="readme.txt"

for f in "$plugin_file" "$readme_file"; do
  if [ ! -f "$f" ]; then
    echo "::error::${f} not found in $(pwd)" >&2
    exit 1
  fi
done

old_version="$(grep -oE '^Stable tag:\s*\S+' "$readme_file" | awk '{print $NF}')"

if [ -z "$old_version" ]; then
  echo "::error::Could not find a 'Stable tag:' line in ${readme_file}." >&2
  exit 1
fi

if ! [[ "$old_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "::error::Stable tag '${old_version}' is not a plain X.Y.Z release version — refusing to guess how to bump it. A wp.org Stable tag must never carry a pre-release suffix." >&2
  exit 1
fi

IFS='.' read -r v_major v_minor v_patch <<< "$old_version"
new_version="${v_major}.${v_minor}.$((v_patch + 1))"

old_esc="$(printf '%s' "$old_version" | sed 's/\./\\./g')"

sed -i -E "s/^(\s*\*\s*Version:\s*)${old_esc}\$/\1${new_version}/" "$plugin_file"
sed -i -E "s/(DABDASH_WOO_VERSION', ')${old_esc}(')/\1${new_version}\2/" "$plugin_file"
sed -i -E "s/^(Stable tag:\s*)${old_esc}\$/\1${new_version}/" "$readme_file"

awk -v new="$new_version" -v sdk="$sdk_version" '
  { print }
  /^== Changelog ==$/ && !done {
    print ""
    print "= " new " ="
    print "* Updated bundled `shadow-software/dabdash-php-sdk` to " sdk "."
    done = 1
  }
' "$readme_file" > "${readme_file}.new"
mv "${readme_file}.new" "$readme_file"

# Fail loudly rather than silently shipping a half-bumped release if any of
# the three substitutions above didn't actually land (e.g. the header/const
# line format drifted from what this script expects).
header="$(grep -oE '^\s*\*\s*Version:\s*\S+' "$plugin_file" | awk '{print $NF}')"
const="$(grep -oE "DABDASH_WOO_VERSION',\s*'[^']+'" "$plugin_file" | grep -oE "'[0-9][^']*'" | tr -d "'")"
stable="$(grep -oE '^Stable tag:\s*\S+' "$readme_file" | awk '{print $NF}')"

if [ "$header" != "$new_version" ] || [ "$const" != "$new_version" ] || [ "$stable" != "$new_version" ]; then
  echo "::error::Version bump did not land everywhere — header=${header} const=${const} stable=${stable}, expected ${new_version}." >&2
  exit 1
fi

if ! grep -qF "= ${new_version} =" "$readme_file"; then
  echo "::error::Changelog entry for ${new_version} was not inserted." >&2
  exit 1
fi

echo "$new_version"
