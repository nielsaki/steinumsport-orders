#!/usr/bin/env bash
# Full WordPress (wp-now, port 9090) — krevst Node.js. Ikki neyðugt fyri
# einfalda form-próving; brúka bin/preview-serve.sh til tað.
set -euo pipefail
cd "$(dirname "$0")/.."
PORT="${PORT:-9090}"
if ! command -v npx &>/dev/null; then
	echo "Krevst: Node.js (brew install node)" >&2
	exit 1
fi
export WP_USE_DOCKER="${WP_USE_DOCKER:-0}"
if [[ "$WP_USE_DOCKER" == "1" ]] && command -v docker &>/dev/null; then
	WP_ENV_PORT="$PORT" npx --yes @wordpress/env start
	exit 0
fi
exec npx --yes @wp-now/wp-now@latest start --port="$PORT"
