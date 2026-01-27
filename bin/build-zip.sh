#!/bin/bash
#
# Build a distributable zip file for WordPress plugin installation.
#
# Usage: ./bin/build-zip.sh [version]
#   version: Optional. Defaults to version from package.json
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

# Get version from package.json if not provided
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep '"version"' package.json | sed 's/.*"version": *"\([^"]*\)".*/\1/')
fi

PLUGIN_SLUG="beer-slurper"
BUILD_DIR="$PROJECT_DIR/release/$VERSION"
ZIP_FILE="$PROJECT_DIR/release/${PLUGIN_SLUG}.${VERSION}.zip"

echo "Building $PLUGIN_SLUG version $VERSION..."

# Clean up any existing build
rm -rf "$BUILD_DIR"
rm -f "$ZIP_FILE"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# Install production Composer dependencies.
echo "Installing production Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Build blocks.
echo "Building blocks..."
npm run build:blocks

# Files and directories to exclude from the zip
EXCLUDES=(
    ".git"
    ".gitignore"
    ".jshintrc"
    ".bowerrc"
    ".claude"
    "node_modules"
    "release"
    "tests"
    "agent-os"
    "theme"
    "bin"
    "src"
    "assets/css/src"
    "assets/js/src"
    "images/src"
    "bootstrap.php"
    "bower.json"
    "composer.json"
    "composer.lock"
    "Gruntfile.js"
    "package.json"
    "package-lock.json"
    "phpunit.xml"
    "phpunit.xml.dist"
    "IMPROVEMENTS.md"
    "*.md"
)

# Build rsync exclude arguments
RSYNC_EXCLUDES=""
for exclude in "${EXCLUDES[@]}"; do
    RSYNC_EXCLUDES="$RSYNC_EXCLUDES --exclude=$exclude"
done

# Copy files to build directory (vendor/ and build/ are now included)
rsync -av $RSYNC_EXCLUDES ./ "$BUILD_DIR/$PLUGIN_SLUG/"

# Restore dev dependencies after build.
echo "Restoring dev Composer dependencies..."
composer install --no-interaction

# Create the zip file
cd "$BUILD_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG"

# Clean up build directory
rm -rf "$BUILD_DIR"

echo ""
echo "Build complete!"
echo "Zip file: $ZIP_FILE"
echo ""

# Show contents
echo "Contents:"
unzip -l "$ZIP_FILE" | head -30
echo "..."
