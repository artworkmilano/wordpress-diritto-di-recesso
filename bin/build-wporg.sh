#!/usr/bin/env bash
# Crea la build per WordPress.org: nessun self-updater (gli aggiornamenti li
# serve WordPress.org per slug). Differisce dalla build GitHub solo per:
#  - header "Update URI" rimosso
#  - costante DDR_GITHUB_REPO svuotata (updater + box aggiornamenti inerti)
set -euo pipefail
SRC="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
DEST="$WORK/diritto-di-recesso"
mkdir -p "$DEST"
git -C "$SRC" archive HEAD | tar -x -C "$DEST"
perl -ni -e 'print unless /^\s*\*\s*Update URI:/' "$DEST/diritto-di-recesso.php"
perl -pi -e "s/define\(\s*'DDR_GITHUB_REPO',\s*'[^']*'\s*\)/define( 'DDR_GITHUB_REPO', '' )/" "$DEST/diritto-di-recesso.php"
OUT="${1:-$SRC/../diritto-di-recesso-wporg.zip}"
rm -f "$OUT"
( cd "$WORK" && zip -rq "$OUT" diritto-di-recesso )
echo "WP.org build: $OUT"
