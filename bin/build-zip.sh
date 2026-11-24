#!/usr/bin/env bash
# Builds giftkit.zip the way the WordPress.org deploy does: everything in
# the repo except what .distignore lists, in a folder named "giftkit".
#
# Usage: bin/build-zip.sh [output-dir]   (default: ./dist)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
out="$(mkdir -p "${1:-$root/dist}" && cd "${1:-$root/dist}" && pwd)"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

rsync -a --exclude-from="$root/.distignore" --exclude=/dist "$root/" "$stage/giftkit/"
rm -f "$out/giftkit.zip"
(cd "$stage" && zip -qr "$out/giftkit.zip" giftkit)
echo "Built $out/giftkit.zip"
