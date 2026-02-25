# Installation auf XAMPP (Localhost)

Diese Anleitung zeigt dir, wie du `txtr.me` lokal mit XAMPP betreibst.

## 1) Voraussetzungen

- Installiertes XAMPP (Apache + PHP)
- Git (optional, für Klonen)
- Browser

## 2) Projekt nach `htdocs` kopieren

Standardpfade:

- Windows: `C:\xampp\htdocs\`
- Linux: `/opt/lampp/htdocs/`
- macOS (XAMPP): `/Applications/XAMPP/htdocs/`

Empfohlenes Zielverzeichnis:

- `htdocs/txtr.me`

Beispiel (wenn du bereits ein Repo hast):

```bash
git clone https://github.com/hcscmedia/txtr.me.git
```

Dann den Ordner `txtr.me` nach `htdocs` verschieben/kopieren.

## 3) Apache starten

- XAMPP Control Panel öffnen
- `Apache` starten

Danach sollte die App erreichbar sein unter:

- `http://localhost/txtr.me/index.php`

## 4) `ADMIN_PASSWORD` in XAMPP setzen

Die App erwartet ein Admin-Passwort als Umgebungsvariable `ADMIN_PASSWORD`.

### Option A (empfohlen): Apache-Konfiguration

1. XAMPP → Apache → `Config` → `Apache (httpd.conf)`
1. Folgende Zeile ergänzen (z. B. am Ende der Datei):

```apacheconf
SetEnv ADMIN_PASSWORD "dein-sehr-starkes-passwort"
```

1. Apache neu starten

### Option B: VirtualHost / vhosts

Wenn du VirtualHosts nutzt, setze `SetEnv` im jeweiligen `<VirtualHost>`.

## 5) Schreibrechte prüfen

Die App schreibt in JSON-Dateien und `uploads/`.

Wichtige Dateien:

- `news_data.json`
- `users.json`
- `messages.json`
- `follows.json`
- `activity_log.json`
- `rate_limits.json`
- `notifications.json`

Zusätzlich:

- Ordner `uploads/` muss beschreibbar sein.

Unter Linux/macOS (falls nötig):

```bash
chmod 755 uploads
chmod 664 news_data.json users.json messages.json follows.json activity_log.json rate_limits.json notifications.json
```

## 6) Funktion prüfen

1. Feed öffnen: `http://localhost/txtr.me/index.php`
2. Health prüfen: `http://localhost/txtr.me/health.php`
3. Admin öffnen: `http://localhost/txtr.me/admin.php`
4. Mit `ADMIN_PASSWORD` einloggen

## 7) Typische Probleme

### `Admin-Passwort ist nicht konfiguriert`

- Prüfen, ob `SetEnv ADMIN_PASSWORD "..."` korrekt gesetzt ist
- Apache vollständig neu starten

### `403 Ungültiger CSRF-Token`

- Seite neu laden
- Cookies/Sessions im Browser nicht blockieren

### JSON-Dateien werden nicht gespeichert

- Rechte auf Projektordner und JSON-Dateien prüfen
- `uploads/`-Ordner vorhanden und beschreibbar machen

### `404` oder falsche Route

- Sicherstellen, dass Projekt unter `htdocs/txtr.me` liegt
- URL mit `/txtr.me/index.php` aufrufen
