# ViennaStateRP Website

Modernes PHP-Website für den FiveM Roleplay Server.

## Setup

### 1. Discord Application erstellen
1. Gehe zu https://discord.com/developers/applications
2. Klicke **New Application** → Name eingeben
3. Gehe zu **OAuth2** → **Redirects** → deine Callback-URL eintragen:
   - Lokal: `http://localhost/callback.php`
   - Produktion: `https://deine-domain.at/callback.php`
4. Kopiere **Client ID** und **Client Secret**

### 2. `config.php` bearbeiten
```php
define('DISCORD_CLIENT_ID',     'DEINE_CLIENT_ID');
define('DISCORD_CLIENT_SECRET', 'DEIN_CLIENT_SECRET');
define('DISCORD_REDIRECT_URI',  'https://deine-domain.at/callback.php');
define('DISCORD_GUILD_ID',      'DEINE_SERVER_ID');
define('DISCORD_INVITE',        'https://discord.gg/DEIN_INVITE');
define('FIVEM_SERVER_IP',       'deine-server-ip');
define('SITE_URL',              'https://deine-domain.at');
```

### 3. Hero-Bild hinzufügen
Lege ein Bild unter `assets/img/hero-bg.jpg` ab (empfohlen: 1920×1080px, GTA V Screenshot).

### 4. PHP-Anforderungen
- PHP >= 8.0
- Extensions: `curl`, `json`, `session`

## Dateistruktur
```
/
├── index.php         ← Startseite
├── login.php         ← Discord OAuth2 Redirect
├── callback.php      ← Discord OAuth2 Callback
├── logout.php        ← Session beenden
├── dashboard.php     ← Eingeloggtes Dashboard
├── config.php        ← Konfiguration (nicht committen!)
└── assets/
    ├── css/style.css
    ├── js/main.js
    └── img/hero-bg.jpg  ← selbst hinzufügen
```

## Deployment (neuester Stand per Git Pull)
Einmalig auf dem Server: `git clone <repo-url>` im Webroot, `.env` eine Ebene darüber ablegen.

- **Automatisch:** GitHub-Webhook auf `https://<domain>/api/deploy_webhook.php`
  (Content type `application/json`, Event „push“, Secret = `DEPLOY_WEBHOOK_SECRET` aus der `.env`).
  Jeder Push auf `main` löst auf dem Server `git pull --ff-only` aus.
- **Manuell:** per SSH `./deploy.sh` im Projektordner.
- **Per Klick:** Admin-Panel → Kachel „Deployment“ (`admin_deploy.php`), Berechtigung `deploy.run` (Standard: ab Owner, in der Rollenverwaltung änderbar).
