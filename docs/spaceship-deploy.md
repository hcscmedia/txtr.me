# Deployment auf Spaceship (PHP)

Diese Anleitung ist für ein klassisches PHP-Hosting bei Spaceship gedacht.

## 1) Domain & Zielverzeichnis

- Domain in Spaceship auf das Webhosting zeigen lassen
- Projekt in das Webroot hochladen (häufig `public_html/`)
- Sicherstellen, dass `index.php` im Webroot liegt

## 2) Dateien hochladen

Upload per Git/FTP/SFTP (je nach Tarif/Panel), z. B. per SFTP:

```bash
rsync -avz --delete \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'node_modules' \
  /local/path/to/txtr.me/ user@server:/path/to/public_html/
```

## 3) Schreibrechte setzen

Folgende Pfade müssen durch PHP beschreibbar sein:

- `uploads/`
- `news_data.json`, `users.json`, `messages.json`, `follows.json`, `activity_log.json`, `rate_limits.json`

Typische Kommandos (SSH):

```bash
cd /path/to/public_html
mkdir -p uploads
chmod 755 uploads
chmod 664 news_data.json users.json messages.json follows.json activity_log.json rate_limits.json
```

## 4) ADMIN_PASSWORD setzen

Die App erwartet `ADMIN_PASSWORD` als Umgebungsvariable.

Je nach Spaceship-Umgebung sind mehrere Wege möglich:

- Hosting-Panel: Environment Variables (`ADMIN_PASSWORD=...`)
- Apache-Kontext (wenn erlaubt):

```apacheconf
SetEnv ADMIN_PASSWORD "dein-sehr-starkes-passwort"
```

Hinweis: Die App liest `ADMIN_PASSWORD` aus `getenv(...)`, `$_ENV` oder `$_SERVER`.

## 5) HTTPS aktivieren

- TLS-Zertifikat im Spaceship-Panel aktivieren
- HTTP auf HTTPS weiterleiten (falls noch nicht aktiv)

## 6) Deployment prüfen

- Startseite öffnen: `https://deine-domain.tld/index.php`
- Health prüfen: `https://deine-domain.tld/health.php`
- Admin-Login testen: `https://deine-domain.tld/admin.php`

## 7) Updates ausrollen

Bei Updates immer:

1. Backup der JSON-Dateien erstellen
2. Neue Version deployen
3. `health.php` prüfen
4. CI-Status der letzten Version checken
