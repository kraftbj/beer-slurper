#!/bin/bash
#
# Build a distributable zip file for the Pint WordPress block theme.
#
# Usage: ./bin/build-theme-zip.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
THEME_DIR="$PROJECT_DIR/theme"

if [ ! -d "$THEME_DIR" ]; then
    echo "Error: theme directory not found at $THEME_DIR"
    exit 1
fi

# Read version from theme style.css
VERSION=$(grep 'Version:' "$THEME_DIR/style.css" | sed 's/.*Version: *//')

THEME_SLUG="pint"
BUILD_DIR="$PROJECT_DIR/release/${THEME_SLUG}-${VERSION}"
ZIP_FILE="$PROJECT_DIR/release/${THEME_SLUG}.${VERSION}.zip"

echo "Building $THEME_SLUG theme version $VERSION..."

# Clean up any existing build
rm -rf "$BUILD_DIR"
rm -f "$ZIP_FILE"
mkdir -p "$BUILD_DIR/$THEME_SLUG"

# Copy theme files
rsync -av \
    --exclude='.DS_Store' \
    "$THEME_DIR/" "$BUILD_DIR/$THEME_SLUG/"

# Create the zip file
cd "$BUILD_DIR"
zip -r "$ZIP_FILE" "$THEME_SLUG"

# Clean up build directory
rm -rf "$BUILD_DIR"

echo ""
echo "Build complete!"
echo "Zip file: $ZIP_FILE"
echo ""

# Show contents
echo "Contents:"
unzip -l "$ZIP_FILE"
