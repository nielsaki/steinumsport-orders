#!/usr/bin/env bash
# Same pattern as https://github.com/nielsaki/afturgjalds-skipan — local
# preview in the browser without a full WordPress install.
#
#   bash bin/preview-serve.sh
#   # then open  http://localhost:9090/
#
# Left: the real form. Right: what would be emailed. Bottom: DB rows
# (SQLite in /tmp). Test mode + dry run are ON — no real mail leaves the box.

set -euo pipefail
cd "$(dirname "$0")/.."
PORT="${PORT:-9090}"
exec php -S "localhost:${PORT}" -t . tests/serve.php
