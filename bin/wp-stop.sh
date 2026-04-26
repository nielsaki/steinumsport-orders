#!/usr/bin/env bash
set -euo pipefail
pkill -f '@wp-now/wp-now' 2>/dev/null || true
if command -v docker &>/dev/null; then
	npx --yes @wordpress/env stop 2>/dev/null || true
fi
