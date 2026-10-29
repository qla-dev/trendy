#!/usr/bin/env bash
# Checks network reachability of the SQL Server from this machine.
# Usage: bash scripts/check-db-connection.sh [host] [port]
# Defaults to DB_HOST / DB_PORT from .env.

cd "$(dirname "$0")/.." || exit 1

env_value() {
    grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'"'"'\r'
}

HOST="${1:-$(env_value DB_HOST)}"
PORT="${2:-$(env_value DB_PORT)}"
HOST="${HOST:-hostBApa1.datalab.ba}"
PORT="${PORT:-50387}"

echo "== Target: $HOST:$PORT"

echo
echo "== DNS"
if command -v getent >/dev/null 2>&1; then
    getent hosts "$HOST" || echo "DNS resolution FAILED"
else
    nslookup "$HOST" || echo "DNS resolution FAILED"
fi

echo
echo "== TCP connect (5s timeout)"
if command -v nc >/dev/null 2>&1; then
    nc -vz -w 5 "$HOST" "$PORT"
else
    timeout 5 bash -c "</dev/tcp/$HOST/$PORT" \
        && echo "Connection to $HOST $PORT succeeded" \
        || echo "Connection to $HOST $PORT FAILED"
fi

echo
echo "== Public outbound IP of this server"
curl -s --max-time 10 ifconfig.me || echo "could not determine"
echo
