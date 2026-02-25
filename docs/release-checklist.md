# Release-Checkliste (Patch)

Diese Checkliste ist für kleine, rückwärtskompatible Releases wie `v1.0.1` gedacht.

## 1) Vorbereitung

- Lokale Änderungen prüfen: `git status`
- Auf `main` sein und Stand aktualisieren: `git checkout main && git pull --ff-only`
- Admin-Passwort für lokale Tests setzen: `export ADMIN_PASSWORD='dein-starkes-passwort'`

## 2) Qualität prüfen

- PHP-Syntax prüfen:

```bash
find . -maxdepth 2 -name "*.php" -print0 | xargs -0 -n1 php -l
```

- Smoke-Test ausführen:

```bash
python3 scripts/smoke_test.py --start-server --base-url http://127.0.0.1:8080 --admin-password "$ADMIN_PASSWORD"
```

- Doku-Checks ausführen:

```bash
npx --yes markdownlint-cli@0.41 "**/*.md" -c .markdownlint.json
npx --yes markdown-link-check README.md -c .markdown-link-check.json
```

## 3) Patch-Release erstellen

- Änderungen committen:

```bash
git add -A
git commit -m "fix: kurze Beschreibung"
git push origin main
```

- Tag + Release veröffentlichen:

```bash
gh release create v1.0.1 --target main --title "v1.0.1" --generate-notes
```

## 4) Nachkontrolle

- CI-Lauf prüfen (GitHub Actions: `CI` muss grün sein)
- Release-Seite öffnen und Notes kurz gegenlesen
- Health-Endpunkt stichprobenartig prüfen:

```bash
curl -s http://127.0.0.1:8080/health.php
```

## 5) Rollback (falls nötig)

- Hotfix auf `main` anwenden, erneut testen, neues Patch-Release erzeugen (z. B. `v1.0.2`)
- Keine bereits veröffentlichten Tags löschen/überschreiben