#!/usr/bin/env bash
# Integration tests: plugin (PHP, real HTTP) <-> real CartRenew WooCommerce route handlers
# (in-memory Supabase, WhatsApp send stubbed). Needs a checkout of the backend repo:
#   BACKEND_DIR=/path/to/cartrenew bash tests/integration/run.sh
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
BACKEND_DIR="${BACKEND_DIR:?set BACKEND_DIR to the cartrenew backend checkout (with node_modules)}"
export PORT="${PORT:-4010}"
sed "s#/home/claude/jkusseoni/cartrenew#$BACKEND_DIR#g; s#/home/claude/cartrenew-woocommerce/tests/integration#$HERE#g" "$HERE/tsconfig.json" > "$HERE/.tsconfig.run.json"
(cd "$BACKEND_DIR" && NODE_PATH="$BACKEND_DIR/node_modules" NEXT_PUBLIC_APP_NAME=CartRenew WOOCOMMERCE_FREE_MESSAGE_LIMIT=3 \
  TSX_TSCONFIG_PATH="$HERE/.tsconfig.run.json" npx tsx "$HERE/server.ts" >/tmp/cartrenew-it-server.log 2>&1) &
SERVER=$!
trap 'kill $SERVER 2>/dev/null; pkill -f integration/server.ts 2>/dev/null; rm -f "$HERE/.tsconfig.run.json"' EXIT
for i in $(seq 1 40); do curl -s "localhost:$PORT/__reset" >/dev/null 2>&1 && break; sleep 0.5; done
php "$HERE/run.php" "${1:-}"
