#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_DIR="$SCRIPT_DIR/Release"
PLUGIN_DIR="$SCRIPT_DIR/Lightwork-plugin"

mkdir -p "$RELEASE_DIR"

VERSION=$(grep -Eo "Version:\s*[0-9.]+" "$PLUGIN_DIR/lightwork-wp-plugin.php" | awk '{print $2}')
if [ -z "$VERSION" ]; then
    VERSION="latest"
fi

ARCHIVE_NAME="lightwork-plugin-${VERSION}.tar.gz"

# Create archive

tar -czf "$RELEASE_DIR/$ARCHIVE_NAME" -C "$PLUGIN_DIR" .

echo "Created $RELEASE_DIR/$ARCHIVE_NAME"
