#!/usr/bin/env bash
# Snapshots every built screen (public/app/screens) into public/screens/*.png
set -e
cd "$(dirname "$0")/.."
npx remotion bundle --out-dir=out/bundle >/dev/null
mkdir -p public/screens
cap() { npx remotion still out/bundle Screen "public/screens/$1.png" --scale="$4" --props="{\"src\":\"app/screens/$1.html\",\"w\":$2,\"h\":$3}" >/dev/null && echo "captured $1"; }
for s in d-dashboard d-nalozi d-plan d-zalihe d-dokumenti; do cap "$s" 1440 900 2; done
cap d-ai 1920 2300 1.5
for s in m-nalog m-operacije m-operacije-2 m-qr m-sirovina m-nfc-scan m-nfc-card; do cap "$s" 390 844 3; done
