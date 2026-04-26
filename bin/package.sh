#!/usr/bin/env bash
# Bygg ein .zip tú kanst uppglóða undir WordPress (Plugins -> Upload).
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="steinum-sport-clothes.zip"
STAGE="$(mktemp -d)"
DEST="${STAGE}/steinum-sport-clothes"
mkdir -p "$DEST"
cp -R steinum-sport-clothes.php includes assets "$DEST"/
rm -f "$OUT"
( cd "$STAGE" && zip -qr "${OLDPWD}/${OUT}" "steinum-sport-clothes" )
rm -rf "$STAGE"
echo "Wrote $OUT"
ls -la "$OUT"
