#!/bin/bash
set -e

# Assembles the mu-plugin release zip. Assumes `npm run build` has already
# produced fresh src/*.css from scss/ (the calling workflow/you runs that
# first) — this script only copies runtime files and zips them.
#
# Assembles the plugin's OWN directory, matching normal WordPress plugin
# zip conventions (a zip containing one folder named after the plugin,
# not pre-wrapped in a "mu-plugins" folder). This is meant to be extracted
# as a SUBFOLDER inside wp-content/mu-plugins/ — see README.md's
# Installation section for why that also requires a small hand-created
# loader file (mu-plugins doesn't auto-load files inside subdirectories):
#   releases/om-admin-color-schemes/om-admin-color-schemes.php
#   releases/om-admin-color-schemes/src/{om-light,om-dark,om-system}.css
#   releases/om-admin-color-schemes/js/editor-brightness-warning.js
#   releases/om-admin-color-schemes/README.md

rm -rf releases;

echo "Making directories...";
mkdir -p releases/om-admin-color-schemes/src releases/om-admin-color-schemes/js;

echo "Copying files...";
cp om-admin-color-schemes.php releases/om-admin-color-schemes/om-admin-color-schemes.php;
echo "- om-admin-color-schemes.php";
cp src/om-light.css src/om-dark.css src/om-system.css releases/om-admin-color-schemes/src/;
echo "- src/*.css";
cp js/editor-brightness-warning.js releases/om-admin-color-schemes/js/editor-brightness-warning.js;
echo "- js/editor-brightness-warning.js";
cp README.md releases/om-admin-color-schemes/README.md;
echo "- README.md";

echo "";
echo "==========================================="
echo "";

ls -lR releases;

echo "";
echo "==========================================="
echo "";

echo "Creating zip...";
echo "";

cd releases/;
zip --test --display-bytes --display-counts -r "om-admin-color-schemes.zip" om-admin-color-schemes;
cd ../;

echo "";
echo "Done."
