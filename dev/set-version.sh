#!/bin/bash
set -e

# Updates the plugin version everywhere it's recorded: the PHP file's own
# header comment, plus package.json/package-lock.json via `npm version`
# (which keeps those two in sync with each other — hand-editing
# package-lock.json's own version fields with a regex isn't safe to
# reproduce, npm's lockfile format records it in more than one place).
#
# Doesn't commit, tag, or push anything itself — see
# .github/workflows/draft-release.yml for how this fits into an actual
# release. Safe to run by hand too, e.g. to preview what a version bump
# would touch without cutting a release.

if [ -z "$1" ]; then
	echo "Usage: sh dev/set-version.sh <version>" >&2
	exit 1
fi

VERSION="$1"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Version must be plain semver (e.g. 1.2.0), got: $VERSION" >&2
	exit 1
fi

echo "Setting version to $VERSION..."

node -e "
	const fs = require('fs');
	const file = 'om-admin-color-schemes.php';
	const contents = fs.readFileSync(file, 'utf8');
	const updated = contents.replace(/^( \* Version:\s+).*/m, '\$1${VERSION}');
	if (updated === contents) {
		console.error('Version header line not found in ' + file + ' — check the regex in dev/set-version.sh');
		process.exit(1);
	}
	fs.writeFileSync(file, updated);
"
echo "- om-admin-color-schemes.php"

npm version "$VERSION" --no-git-tag-version --allow-same-version > /dev/null
echo "- package.json / package-lock.json"

echo ""
echo "Done."
