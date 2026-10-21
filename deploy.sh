#!/usr/bin/env bash
# Manuelles Deployment per SSH:  ./deploy.sh [branch]
# Holt den neuesten Stand von GitHub (nur Fast-Forward, überschreibt keine lokalen Änderungen).
set -euo pipefail
cd "$(dirname "$0")"
BRANCH="${1:-main}"

git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"

# PHP-Syntax der geänderten Dateien prüfen (falls php vorhanden)
if command -v php >/dev/null 2>&1; then
  git diff --name-only ORIG_HEAD HEAD -- '*.php' 2>/dev/null | while read -r f; do
    [ -f "$f" ] && php -l "$f" >/dev/null
  done
fi
rm -f "${TMPDIR:-/tmp}"/vsrp_*.json
echo "Deployment fertig: $(git log --oneline -1)"
