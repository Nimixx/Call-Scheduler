#!/bin/bash
#
# Release script for Call Scheduler WordPress plugin
# Handles version bumping, tagging, and triggering the release workflow
#
# Usage: ./bin/release.sh <version> [--dry-run]
# Example: ./bin/release.sh 1.0.0
#          ./bin/release.sh 1.1.0 --dry-run
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Plugin info
PLUGIN_SLUG="call-scheduler"
PLUGIN_FILE="call-scheduler.php"

# Get script directory and plugin root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PLUGIN_DIR"

# Parse arguments
VERSION=""
DRY_RUN=false

for arg in "$@"; do
    case $arg in
        --dry-run)
            DRY_RUN=true
            ;;
        *)
            if [ -z "$VERSION" ]; then
                VERSION="$arg"
            fi
            ;;
    esac
done

# Validate version
if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Version is required${NC}"
    echo "Usage: ./bin/release.sh <version> [--dry-run]"
    echo "Example: ./bin/release.sh 1.0.0"
    exit 1
fi

# Validate version format (semver)
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo -e "${RED}Error: Invalid version format. Use semantic versioning (e.g., 1.0.0, 1.1.0-beta.1)${NC}"
    exit 1
fi

echo -e "${GREEN}Release Script for ${PLUGIN_SLUG}${NC}"
echo "========================================"
echo -e "Version:  ${BLUE}${VERSION}${NC}"
echo -e "Dry run:  ${DRY_RUN}"
echo ""

# Check we're on main or develop branch
CURRENT_BRANCH=$(git branch --show-current)
if [[ "$CURRENT_BRANCH" != "main" && "$CURRENT_BRANCH" != "develop" ]]; then
    echo -e "${RED}Error: Releases must be made from 'main' or 'develop' branch${NC}"
    echo -e "Current branch: ${CURRENT_BRANCH}"
    exit 1
fi

# Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo -e "${RED}Error: You have uncommitted changes${NC}"
    git status --short
    exit 1
fi

# Check if tag already exists
if git rev-parse "v${VERSION}" >/dev/null 2>&1; then
    echo -e "${RED}Error: Tag v${VERSION} already exists${NC}"
    exit 1
fi

# Get current version from plugin file
CURRENT_VERSION=$(grep -E "^\s*\*\s*Version:" "$PLUGIN_FILE" | sed 's/.*Version:\s*//' | tr -d '[:space:]')
echo -e "Current version: ${CURRENT_VERSION}"
echo -e "New version:     ${VERSION}"
echo ""

# Run tests first
echo -e "${YELLOW}Running tests...${NC}"
php tests/standalone-tests.php
php tests/settings-standalone-tests.php
echo -e "${GREEN}Tests passed!${NC}"
echo ""

# Update version in files
echo -e "${YELLOW}Updating version in files...${NC}"

if [ "$DRY_RUN" = true ]; then
    echo -e "${BLUE}[DRY RUN] Would update:${NC}"
    echo "  - $PLUGIN_FILE: Version header"
    echo "  - $PLUGIN_FILE: CS_VERSION constant"
else
    # Update plugin header version
    sed -i.bak "s/Version:\s*${CURRENT_VERSION}/Version: ${VERSION}/" "$PLUGIN_FILE"

    # Update CS_VERSION constant
    sed -i.bak "s/define('CS_VERSION', '${CURRENT_VERSION}')/define('CS_VERSION', '${VERSION}')/" "$PLUGIN_FILE"

    # Remove backup files
    rm -f "${PLUGIN_FILE}.bak"

    echo -e "${GREEN}Version updated in plugin files${NC}"
fi

echo ""

# Build the plugin
echo -e "${YELLOW}Building plugin...${NC}"
if [ "$DRY_RUN" = true ]; then
    echo -e "${BLUE}[DRY RUN] Would run: ./bin/build.sh ${VERSION}${NC}"
else
    ./bin/build.sh "$VERSION"
fi
echo ""

# Commit version bump
echo -e "${YELLOW}Committing version bump...${NC}"
if [ "$DRY_RUN" = true ]; then
    echo -e "${BLUE}[DRY RUN] Would commit: 'Bump version to ${VERSION}'${NC}"
else
    git add "$PLUGIN_FILE"
    git commit -m "Bump version to ${VERSION}

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
fi

# Create and push tag
echo -e "${YELLOW}Creating tag v${VERSION}...${NC}"
if [ "$DRY_RUN" = true ]; then
    echo -e "${BLUE}[DRY RUN] Would create tag: v${VERSION}${NC}"
    echo -e "${BLUE}[DRY RUN] Would push to origin${NC}"
else
    git tag -a "v${VERSION}" -m "Release v${VERSION}"

    echo -e "${YELLOW}Pushing to origin...${NC}"
    git push origin "$CURRENT_BRANCH"
    git push origin "v${VERSION}"
fi

echo ""
echo -e "${GREEN}========================================"
echo -e "Release v${VERSION} complete!"
echo -e "========================================${NC}"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo -e "${BLUE}This was a dry run. No changes were made.${NC}"
else
    echo "Next steps:"
    echo "  1. GitHub Actions will automatically create the release"
    echo "  2. Download the zip from: https://github.com/Nimixx/Call-Scheduler/releases/tag/v${VERSION}"
    echo "  3. Upload to WordPress or distribute to clients"
    echo ""
    echo "Build artifact: build/${PLUGIN_SLUG}-${VERSION}.zip"
fi
