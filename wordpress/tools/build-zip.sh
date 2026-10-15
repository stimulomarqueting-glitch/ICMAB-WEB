#!/bin/bash
# Builds the installable theme zip: wordpress/dist/matsurfer.zip
#   bash wordpress/tools/build-zip.sh
# Regenerates the original-copy export first so the zip always ships the
# current Astro content, then lints every PHP file.
set -euo pipefail
cd "$(dirname "$0")/../.."
node wordpress/tools/export-content.mjs >/dev/null
node wordpress/tools/build-settings-defaults.mjs >/dev/null
fail=0
while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null 2>&1 || { php -l "$f"; fail=1; }
done < <(find wordpress/matsurfer -name '*.php' -print0)
[ "$fail" -eq 0 ] || { echo "PHP syntax errors — zip not built"; exit 1; }
mkdir -p wordpress/dist
rm -f wordpress/dist/matsurfer.zip
(cd wordpress && zip -qr dist/matsurfer.zip matsurfer -x '*.DS_Store' -x 'matsurfer/node_modules/*')
ls -lh wordpress/dist/matsurfer.zip
