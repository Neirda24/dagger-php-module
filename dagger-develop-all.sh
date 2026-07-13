#!/usr/bin/env bash
# Runs `dagger develop` in every module directory (any dir with a dagger.json
# at the repo root level), regenerating sdk/ and vendor/. `app` depends on
# the other modules so it always runs last.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

modules=()
for dir in */; do
    dir="${dir%/}"
    [[ -f "$dir/dagger.json" ]] || continue
    [[ "$dir" == "app" ]] && continue
    [[ "$dir" == "dagger" ]] && continue
    modules+=("$dir")
done
modules+=("app")

for module in "${modules[@]}"; do
    echo "==> Module: $module"
    (cd "${module}" && dagger develop)
done
