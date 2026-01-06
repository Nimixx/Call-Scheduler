#!/bin/bash
#
# Build script for Call Scheduler WordPress plugin
# Creates a production-ready zip file excluding development files
#
# Usage: ./bin/build.sh [version]
# Example: ./bin/build.sh 1.0.0
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin info
PLUGIN_SLUG="call-scheduler"
PLUGIN_FILE="call-scheduler.php"

# Get script directory and plugin root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

# Change to plugin directory
cd "$PLUGIN_DIR"

# Get version from argument or extract from plugin file
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep -E "^\s*\*\s*Version:" "$PLUGIN_FILE" | sed 's/.*Version:\s*//' | tr -d '[:space:]')
fi

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not determine version${NC}"
    exit 1
fi

echo -e "${GREEN}Building ${PLUGIN_SLUG} v${VERSION}${NC}"
echo "========================================"

# Build directory
BUILD_DIR="${PLUGIN_DIR}/build"
DIST_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
ZIP_FILE="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

# Clean previous build
echo -e "${YELLOW}Cleaning previous build...${NC}"
rm -rf "$BUILD_DIR"
mkdir -p "$DIST_DIR"

# Copy all files first
echo -e "${YELLOW}Copying files...${NC}"
rsync -av --quiet \
    --exclude='.git' \
    --exclude='build' \
    --exclude='node_modules' \
    . "$DIST_DIR/"

# Remove files listed in .distignore
echo -e "${YELLOW}Removing development files...${NC}"
if [ -f ".distignore" ]; then
    # First pass: collect exclusions (lines starting with !)
    declare -a KEEP_FILES=()

    while IFS= read -r line || [ -n "$line" ]; do
        [[ "$line" =~ ^#.*$ ]] && continue
        [[ -z "$line" ]] && continue

        if [[ "$line" =~ ^! ]]; then
            KEEP_FILES+=("${line:1}")
        fi
    done < ".distignore"

    # Second pass: remove files (skip exclusions)
    while IFS= read -r line || [ -n "$line" ]; do
        # Skip comments, empty lines, and exclusions
        [[ "$line" =~ ^#.*$ ]] && continue
        [[ -z "$line" ]] && continue
        [[ "$line" =~ ^! ]] && continue

        # Remove the file/directory
        pattern="${DIST_DIR}/${line}"

        # Handle glob patterns
        if compgen -G "$pattern" > /dev/null 2>&1; then
            for file in $pattern; do
                filename=$(basename "$file")
                # Check if file should be kept
                should_keep=false
                for keep in "${KEEP_FILES[@]}"; do
                    if [[ "$filename" == "$keep" ]]; then
                        should_keep=true
                        break
                    fi
                done

                if [ "$should_keep" = false ]; then
                    rm -rf "$file" 2>/dev/null || true
                fi
            done
        fi
    done < ".distignore"
fi

# Install production composer dependencies only (if composer.json exists)
if [ -f "${DIST_DIR}/composer.json" ]; then
    echo -e "${YELLOW}Installing production dependencies...${NC}"
    cd "$DIST_DIR"

    # Check if there are any production dependencies
    if grep -q '"require"' composer.json && ! grep -q '"require": {}' composer.json; then
        composer install --no-dev --optimize-autoloader --no-interaction --quiet 2>/dev/null || true
    fi

    # Remove composer files from distribution
    rm -f composer.json composer.lock

    cd "$PLUGIN_DIR"
fi

# Remove empty directories
echo -e "${YELLOW}Cleaning up empty directories...${NC}"
find "$DIST_DIR" -type d -empty -delete 2>/dev/null || true

# Verify plugin file exists
if [ ! -f "${DIST_DIR}/${PLUGIN_FILE}" ]; then
    echo -e "${RED}Error: Plugin file not found in build${NC}"
    exit 1
fi

# Create zip
echo -e "${YELLOW}Creating zip archive...${NC}"
cd "$BUILD_DIR"
zip -r -q "$ZIP_FILE" "$PLUGIN_SLUG"

# Calculate file size
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)

# Cleanup
rm -rf "$DIST_DIR"

echo ""
echo -e "${GREEN}Build complete!${NC}"
echo "========================================"
echo -e "Version:  ${VERSION}"
echo -e "File:     ${ZIP_FILE}"
echo -e "Size:     ${ZIP_SIZE}"
echo ""

# List contents for verification
echo -e "${YELLOW}Archive contents:${NC}"
unzip -l "$ZIP_FILE" | head -30
echo "..."
echo ""

# Verify no dev files leaked
echo -e "${YELLOW}Checking for leaked dev files...${NC}"
LEAKED=""

# Check for common dev files (use word boundaries to avoid false positives)
for check in "/tests/" "/phpunit" "/.git/" "/node_modules/" "/composer.json" "/CLAUDE.md" "/.env" "/vendor/"; do
    if unzip -l "$ZIP_FILE" 2>/dev/null | grep -qE "$check"; then
        LEAKED="${LEAKED}${check} "
    fi
done

if [ -n "$LEAKED" ]; then
    echo -e "${RED}WARNING: Found dev files in archive: ${LEAKED}${NC}"
    exit 1
else
    echo -e "${GREEN}No development files found in archive${NC}"
fi

echo ""
echo -e "${GREEN}Ready for release!${NC}"
