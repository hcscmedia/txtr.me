# Contributing

Danke für Beiträge zu `txtr.me`.

## Entwicklungsprinzipien

- Kleine, fokussierte Änderungen pro PR
- Keine unnötigen Refactorings ohne fachlichen Anlass
- Sicherheitsrelevante Änderungen immer mit kurzer Begründung in der PR
- Runtime-Daten (`*.json` mit Live/Test-Inhalten) nicht unbeabsichtigt committen

## Lokales Setup

```bash
export ADMIN_PASSWORD='dein-starkes-passwort'
php -S 127.0.0.1:8080
```

App öffnen: `http://127.0.0.1:8080/index.php`

## Qualitätschecks vor jedem Push

### PHP-Lint

```bash
find . -maxdepth 2 -name "*.php" -print0 | xargs -0 -n1 php -l
```

### Smoke-Test

```bash
python3 scripts/smoke_test.py --start-server --base-url http://127.0.0.1:8080 --admin-password "$ADMIN_PASSWORD"
```

### Doku-Checks

```bash
npx --yes markdownlint-cli@0.41 "**/*.md" -c .markdownlint.json
npx --yes markdown-link-check README.md -c .markdown-link-check.json
```

## Git-Workflow

1. Branch von `main` erstellen
2. Änderungen umsetzen + lokal prüfen
3. Commit mit präziser Message (z. B. `fix: ...`, `docs: ...`)
4. PR öffnen und CI abwarten
5. Nach Merge optional Patch-Release erzeugen

## Release-Prozess

Für den genauen Ablauf siehe:

- `docs/release-checklist.md`

Kurzform:

```bash
git checkout main && git pull --ff-only
git add -A
git commit -m "fix: kurze Beschreibung"
git push origin main
gh release create vX.Y.Z --target main --title "vX.Y.Z" --generate-notes
```

## Pull Request Checkliste

- [ ] Scope klar und klein gehalten
- [ ] Relevante lokale Checks erfolgreich
- [ ] Keine sensiblen Daten/Secrets committed
- [ ] Doku angepasst, wenn Verhalten geändert wurde