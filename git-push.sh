#!/bin/bash

echo ""
echo "🔍 Git-Status prüfen..."
git status
echo ""

# Speicherordner explizit einbeziehen (z. B. Vereinslogos)
echo "📂 Sicherstellen, dass Uploads mitgepusht werden..."
git add storage/app/public -f
echo "✔️  Upload-Ordner hinzugefügt"
echo ""

# Alle Änderungen hinzufügen
echo "📦 Alle weiteren Änderungen hinzufügen..."
git add .
echo "✔️  Dateien hinzugefügt"
echo ""

# Commit-Nachricht eingeben
echo "✍️  Bitte Commit-Nachricht eingeben:"
read -p "Nachricht: " msg
echo ""

# Commit & Push
echo "📤 Änderungen werden committed und gepusht..."
git commit -m "$msg"
git push origin main
echo ""

echo "✅ Fertig! Änderungen sind auf GitHub."
