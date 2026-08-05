#!/bin/sh
set -e

ROOT="$(CDPATH= cd -- "$(dirname "$0")" && pwd)"
cd "$ROOT"

rm -rf samedaycourier.zip
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT INT TERM

mkdir "$STAGE/samedaycourier"

# Copy into a temp dir so we never cp ./ into itself (fails on modern coreutils).
tar -cf - \
  --exclude='./.git' \
  --exclude='./.github' \
  --exclude='./log' \
  --exclude='./samedaycourier.zip' \
  --exclude='./build.sh' \
  . | tar -xf - -C "$STAGE/samedaycourier"

# Same as previous build.sh: omit config.xml from the release zip.
rm -f "$STAGE/samedaycourier/config.xml"

# -X omits Unix UID/GID/extra fields that break ZipArchive on older PS 1.6 PHP.
(cd "$STAGE" && zip -r -X "$ROOT/samedaycourier.zip" samedaycourier)
