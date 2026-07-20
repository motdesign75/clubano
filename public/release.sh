#!/bin/bash

VERSION=$1
DATE=$(date +%Y-%m-%d)

if [ -z "$VERSION" ]; then
  echo "⚠️  Bitte gib eine Versionsnummer an (z. B. ./release.sh 0.9.0)"
  exit 1
fi

# 1. Git-Tag setzen
git tag -a "v$VERSION" -m "Release $VERSION"
git push origin "v$VERSION"
echo "✅ Git-Tag v$VERSION gesetzt und gepusht."

# 2. In config/app.php eintragen
sed -i '' "s/'version' => '.*'/'version' => '$VERSION'/" config/app.php
echo "✅ Version in config/app.php auf $VERSION aktualisiert."

# 3. Changelog-Eintrag hinzufügen
echo -e "\n## [$VERSION] – $DATE\n- Neues Release\n" >> CHANGELOG.md
echo "✅ CHANGELOG.md aktualisiert."

echo "🎉 Release $VERSION abgeschlossen!"
