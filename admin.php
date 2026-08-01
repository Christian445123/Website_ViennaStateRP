<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/fivem_api.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/changelog.php';

require_login();
if (!has_permission('admin')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

$user     = current_user();
$settings = settings_load();

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $user): string {
        if (empty($user['avatar']) || empty($user['id'])) return 'https://cdn.discordapp.com/embed/avatars/0.png';
        return 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $user['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.6rem; font-weight: 900; }
    .admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .admin-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
    .admin-card-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: var(--text-2); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .admin-full { grid-column: 1 / -1; }
    /* Toggle Switch */
    .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); }
    .toggle-row:last-child { border-bottom: none; }
    .toggle-label { font-size: 0.9rem; color: var(--text); }
    .toggle-sub { font-size: 0.75rem; color: var(--text-3); margin-top: 2px; }
    .toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .toggle input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; inset: 0; background: rgba(255,255,255,0.1); border-radius: 24px; cursor: pointer; transition: 0.2s; }
    .toggle-slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; }
    .toggle input:checked + .toggle-slider { background: var(--green); }
    .toggle input:checked + .toggle-slider::before { transform: translateX(20px); }
    /* FiveM Data Table */
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .data-table th { text-align: left; color: var(--text-3); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1.5px; padding: 0 8px 10px; font-weight: 600; }
    .data-table td { padding: 9px 8px; border-top: 1px solid var(--border); vertical-align: top; }
    .data-table td:first-child { color: var(--text-2); width: 35%; }
    .data-table td:last-child { color: var(--text); font-family: 'Orbitron', sans-serif; font-size: 0.82rem; word-break: break-all; }
    .data-val-large { font-size: 1.4rem !important; color: var(--gold) !important; }
    .data-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 700; font-family: 'Rajdhani', sans-serif; }
    .badge-online  { background: rgba(34,197,94,0.15);  color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
    .badge-offline { background: rgba(239,68,68,0.15);  color: var(--red);   border: 1px solid rgba(239,68,68,0.3); }
    .res-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .res-tag { background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 0.72rem; color: var(--text-2); font-family: 'Rajdhani', sans-serif; }
    /* Save button */
    .save-bar { position: fixed; bottom: 0; left: 0; right: 0; background: rgba(10,10,15,0.95); border-top: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: flex-end; gap: 12px; z-index: 100; backdrop-filter: blur(10px); display: none; }
    .save-bar.visible { display: flex; }
    #saveMsg { font-size: 0.85rem; color: var(--green); }
    @media(max-width:768px){ .admin-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<nav class="navbar scrolled">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="players.php">Server Status</a></li>
      <li><a href="admin.php" class="active">Admin</a></li>
      <li><a href="db_admin.php">DB Browser</a></li>
    </ul>
    <div class="nav-actions">
      <a href="dashboard.php" class="btn btn-ghost">
        <img src="<?= avatarUrl($user) ?>" alt="" class="nav-avatar" />
        <?= htmlspecialchars($user['global_name']) ?>
      </a>
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
  </div>
</nav>

<main class="admin-page">
  <div class="container">
    <div class="admin-header">
      <div>
        <div class="admin-title">⚙️ Admin <span class="accent">Panel</span></div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Eingeloggt als <?= htmlspecialchars($user['global_name']) ?> · Rolle: <strong><?= htmlspecialchars($user['role'] ?? 'admin') ?></strong></p>
      </div>
      <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
    </div>

    <div class="admin-grid">

      <!-- Live FiveM Daten -->
      <div class="admin-card admin-full" id="fivemDataCard">
        <div class="admin-card-title">
          📡 FiveM Server — Live Daten
          <span style="margin-left:auto;font-size:.78rem;color:var(--text-3)" id="dataTimestamp"></span>
          <button onclick="loadFiveMData()" class="btn btn-outline" style="padding:4px 12px;font-size:.78rem;margin-left:8px">↻ Aktualisieren</button>
        </div>
        <div id="fivemDataContent" style="color:var(--text-2);font-size:.9rem">Lade Daten...</div>
      </div>

      <!-- Öffentliche Anzeige – Statusseite -->
      <div class="admin-card">
        <div class="admin-card-title">👁️ Statusseite – Sichtbarkeit</div>

        <div class="toggle-row">
          <div>
            <div class="toggle-label">Spieleranzahl</div>
            <div class="toggle-sub">Aktuelle Spieler auf players.php</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_player_count" <?= $settings['show_player_count'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Max Slots</div>
            <div class="toggle-sub">Maximale Serverkapazität</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_max_slots" <?= $settings['show_max_slots'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Auslastung %</div>
            <div class="toggle-sub">Prozentuale Serverauslastung</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_load_percent" <?= $settings['show_load_percent'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Polizei-Anzeige</div>
            <div class="toggle-sub">Polizisten im Dienst</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_jobs_police" <?= $settings['show_jobs_police'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Feuerwehr-Anzeige</div>
            <div class="toggle-sub">Feuerwehr im Dienst</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_jobs_fire" <?= $settings['show_jobs_fire'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Berufsrettung-Anzeige</div>
            <div class="toggle-sub">Berufsrettung Wien im Dienst</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_jobs_ambulance" <?= $settings['show_jobs_ambulance'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Mechaniker-Anzeige</div>
            <div class="toggle-sub">Mechaniker im Dienst</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_jobs_mechanic" <?= $settings['show_jobs_mechanic'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
      </div>

      <!-- Startseite & Wartung -->
      <div class="admin-card">
        <div class="admin-card-title">🏠 Startseite & Wartungsmodus</div>

        <div class="toggle-row">
          <div>
            <div class="toggle-label">Online-Badge im Hero</div>
            <div class="toggle-sub">Server Online/Offline Badge auf index.php</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_hero_status" <?= $settings['show_hero_status'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Spieleranzahl im Hero</div>
            <div class="toggle-sub">Live-Spielerzahl auf der Startseite</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="show_hero_players" <?= $settings['show_hero_players'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row" style="border-top: 1px solid rgba(239,68,68,0.2); margin-top: 12px; padding-top: 16px;">
          <div>
            <div class="toggle-label" style="color:var(--red)">⚠️ Wartungsmodus</div>
            <div class="toggle-sub">Zeigt Wartungshinweis für alle Besucher</div>
          </div>
          <label class="toggle"><input type="checkbox" data-key="maintenance_mode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
        </div>
        <div class="toggle-row" style="flex-direction:column;align-items:flex-start;gap:8px">
          <div class="toggle-label">Wartungsnachricht</div>
          <textarea data-key="maintenance_message" rows="2"
            style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-family:inherit;font-size:.88rem;resize:vertical"
          ><?= htmlspecialchars($settings['maintenance_message']) ?></textarea>
        </div>
      </div>

    </div><!-- /.admin-grid -->

    <!-- ═══════════════════════════════════════════════════
         FRAKTIONEN-VERWALTUNG
    ═══════════════════════════════════════════════════ -->
    <div style="margin-top:40px;border-top:1px solid var(--border);padding-top:36px" id="fraktionenSection">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900">🏙 Fraktionen <span style="color:#e8b86d">Verwaltung</span></div>
          <p style="color:#64748b;font-size:.78rem;margin-top:4px">Fraktionen auf der Startseite bearbeiten, hinzufügen oder löschen</p>
        </div>
        <button onclick="openFrakModal()" class="btn btn-primary" style="font-size:.85rem">+ Fraktion hinzufügen</button>
      </div>

      <div id="frakList" style="display:flex;flex-direction:column;gap:8px">
        <div style="color:var(--text-2);font-size:.9rem">Lade Fraktionen...</div>
      </div>
    </div>

    <!-- Modal: Fraktion bearbeiten / neu -->
    <div id="frakModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:540px;width:90%;max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
          <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900" id="frakModalTitle">Fraktion bearbeiten</div>
          <button onclick="closeFrakModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1">×</button>
        </div>
        <input type="hidden" id="frakId" value="0" />
        <div style="display:grid;grid-template-columns:80px 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Emoji</label>
            <input type="text" id="frakEmoji" placeholder="👮" maxlength="5"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:1.3rem;text-align:center" />
          </div>
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Name</label>
            <input type="text" id="frakName" placeholder="Landespolizeidirektion Wien" maxlength="100"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 120px;gap:14px;margin-bottom:14px">
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Typ-Label</label>
            <input type="text" id="frakTyp" placeholder="STAATLICH · LEGAL" maxlength="60"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
          </div>
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Farbe</label>
            <input type="color" id="frakFarbe" value="#3b82f6"
              style="width:100%;height:39px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:2px 4px;cursor:pointer" />
          </div>
        </div>
        <div style="margin-bottom:14px">
          <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Beschreibung</label>
          <textarea id="frakBeschreibung" rows="3" placeholder="Kurze Beschreibung der Fraktion..."
            style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem;resize:vertical"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Slots-Label</label>
            <input type="text" id="frakSlots" placeholder="Offen" maxlength="50"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
          </div>
          <div style="display:flex;align-items:flex-end;padding-bottom:2px">
            <label class="toggle" style="margin-right:10px"><input type="checkbox" id="frakAktiv" checked /><span class="toggle-slider"></span></label>
            <span style="font-size:.9rem;color:var(--text)">Sichtbar</span>
          </div>
        </div>
        <div id="frakModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button onclick="closeFrakModal()" class="btn btn-outline">Abbrechen</button>
          <button onclick="saveFraktion()" class="btn btn-primary" id="frakSaveBtn">💾 Speichern</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         CHANGELOG-VERWALTUNG
    ═══════════════════════════════════════════════════ -->
    <div style="margin-top:40px;border-top:1px solid var(--border);padding-top:36px" id="changelogSection">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900">📝 Changelog <span style="color:#e8b86d">Verwaltung</span></div>
          <p style="color:#64748b;font-size:.78rem;margin-top:4px">Eintr&auml;ge f&uuml;r die Changelog-Seite anlegen, bearbeiten oder l&ouml;schen. Der neueste ver&ouml;ffentlichte Eintrag erscheint auf der Startseite. Sobald ein Eintrag sichtbar wird, geht automatisch eine Discord-Ank&uuml;ndigung raus.</p>
        </div>
        <div style="display:flex;gap:8px">
          <button onclick="processDueChangelogs()" class="btn btn-outline" style="font-size:.85rem" id="clProcessDueBtn">🔔 F&auml;llige jetzt senden</button>
          <button onclick="openClModal()" class="btn btn-primary" style="font-size:.85rem">+ Eintrag hinzuf&uuml;gen</button>
        </div>
      </div>

      <div id="clList" style="display:flex;flex-direction:column;gap:8px">
        <div style="color:var(--text-2);font-size:.9rem">Lade Changelog...</div>
      </div>
    </div>

    <!-- Modal: Changelog-Eintrag bearbeiten / neu -->
    <div id="clModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:560px;width:90%;max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
          <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900" id="clModalTitle">Eintrag bearbeiten</div>
          <button onclick="closeClModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1">×</button>
        </div>
        <input type="hidden" id="clId" value="0" />
        <div style="display:grid;grid-template-columns:120px 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Version</label>
            <input type="text" id="clVersion" placeholder="v1.2.0" maxlength="30"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
          </div>
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Titel</label>
            <input type="text" id="clTitle" placeholder="Neues Bankensystem" maxlength="150"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 160px;gap:14px;margin-bottom:14px">
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Kategorie</label>
            <select id="clTag" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem">
              <?php foreach (CHANGELOG_TAGS as $tagKey => $tagInfo): ?>
                <option value="<?= htmlspecialchars($tagKey) ?>"><?= htmlspecialchars($tagInfo['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Datum</label>
            <input type="date" id="clDate"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
          </div>
        </div>
        <div style="margin-bottom:14px">
          <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Änderungen <span style="color:#4b5563;font-weight:400">(ein Punkt pro Zeile)</span></label>
          <textarea id="clContent" rows="6" placeholder="Neues Bankensystem hinzugefügt&#10;Bug beim Einloggen behoben&#10;Performance verbessert"
            style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem;resize:vertical"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px">
          <div style="display:flex;align-items:center;gap:10px;padding-top:20px">
            <label class="toggle"><input type="checkbox" id="clPublished" checked /><span class="toggle-slider"></span></label>
            <span style="font-size:.9rem;color:var(--text)">Veröffentlicht</span>
          </div>
          <div>
            <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Sichtbar ab <span style="color:#4b5563;font-weight:400">(optional)</span></label>
            <input type="datetime-local" id="clVisibleAt"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.85rem" />
          </div>
        </div>
        <p style="color:#4b5563;font-size:.75rem;margin-bottom:20px">Leer lassen = sofort sichtbar sobald ver&ouml;ffentlicht. Ein Zeitpunkt in der Zukunft versteckt den Eintrag bis dahin und macht ihn danach automatisch sichtbar (inkl. Discord-Ank&uuml;ndigung).</p>
        <div id="clModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button onclick="closeClModal()" class="btn btn-outline">Abbrechen</button>
          <button onclick="saveChangelog()" class="btn btn-primary" id="clSaveBtn">💾 Speichern</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         DATENBANKVERBINDUNG (Admin only)
    ═══════════════════════════════════════════════════ -->
    <div style="margin-top:40px;border-top:1px solid var(--border);padding-top:36px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900">🗄 MariaDB <span style="color:#e8b86d">Verbindung</span></div>
          <p style="color:#64748b;font-size:.78rem;margin-top:4px">Credentials werden AES-256-GCM verschlüsselt in <code style="color:#86efac;font-size:.72rem">includes/db_config.enc</code> gespeichert</p>
        </div>
        <div style="display:flex;gap:8px">
          <span id="dbStatusBadge" style="display:none;padding:4px 12px;border-radius:999px;font-size:.75rem;font-weight:700"></span>
          <a href="db_admin.php" class="btn btn-outline" style="font-size:.82rem;padding:7px 16px">🗃 DB-Browser →</a>
        </div>
      </div>

      <?php
      $currentCfg = db_config_load();
      $cfgSource  = $currentCfg ? 'db_config.enc' : '.env';
      ?>
      <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:24px">
        <div style="font-size:.72rem;color:#64748b;margin-bottom:16px;display:flex;align-items:center;gap:8px">
          <span>Aktive Quelle:</span>
          <span style="background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac;border-radius:4px;padding:2px 8px;font-weight:700"><?= htmlspecialchars($cfgSource) ?></span>
          <?php if ($currentCfg && isset($currentCfg['updated_at'])): ?>
          <span style="color:#4b5563">· Zuletzt gespeichert: <?= htmlspecialchars($currentCfg['updated_at']) ?></span>
          <?php endif; ?>
        </div>

        <form id="dbConfigForm">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
          <div style="display:grid;grid-template-columns:1fr 120px;gap:14px;margin-bottom:14px">
            <div>
              <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Host / IP-Adresse</label>
              <input type="text" name="host" id="dbHost"
                value="<?= htmlspecialchars($currentCfg['host'] ?? env('DB_HOST','127.0.0.1')) ?>"
                placeholder="127.0.0.1 oder mariadb-hostname"
                style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
            </div>
            <div>
              <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Port</label>
              <input type="number" name="port" id="dbPort"
                value="<?= htmlspecialchars($currentCfg['port'] ?? env('DB_PORT','3306')) ?>"
                placeholder="3306"
                style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Datenbankname</label>
              <input type="text" name="name" id="dbName"
                value="<?= htmlspecialchars($currentCfg['name'] ?? env('DB_NAME','')) ?>"
                placeholder="viennastaterp"
                style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
            </div>
            <div>
              <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Zeichensatz</label>
              <select name="charset" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem">
                <option value="utf8mb4" <?= ($currentCfg['charset'] ?? 'utf8mb4') === 'utf8mb4' ? 'selected' : '' ?>>utf8mb4 (empfohlen)</option>
                <option value="utf8" <?= ($currentCfg['charset'] ?? '') === 'utf8' ? 'selected' : '' ?>>utf8</option>
                <option value="latin1" <?= ($currentCfg['charset'] ?? '') === 'latin1' ? 'selected' : '' ?>>latin1</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <div>
              <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Benutzername</label>
              <input type="text" name="user" id="dbUser"
                value="<?= htmlspecialchars($currentCfg['user'] ?? env('DB_USER','')) ?>"
                placeholder="db_user" autocomplete="off"
                style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
            </div>
            <div>
              <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">
                Passwort <span style="color:#4b5563;font-weight:400">(leer lassen = unverändert)</span>
              </label>
              <div style="position:relative">
                <input type="password" name="pass" id="dbPass"
                  placeholder="••••••••" autocomplete="new-password"
                  style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 36px 9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
                <button type="button" onclick="toggleDbPw()"
                  style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer">👁</button>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:10px;align-items:center">
            <button type="button" onclick="testDbConnection()" class="btn btn-outline" style="padding:9px 20px">
              🔌 Verbindung testen
            </button>
            <button type="submit" class="btn btn-primary" style="padding:9px 20px">
              💾 Verschlüsselt speichern
            </button>
            <span id="dbMsg" style="font-size:.82rem;display:none"></span>
          </div>
        </form>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
          <div style="font-size:.7rem;color:#4b5563;display:flex;flex-wrap:wrap;gap:16px">
            <span>🔐 Verschlüsselung: AES-256-GCM</span>
            <span>🗝 Key: VSRP_SECRET_KEY (.env) oder vsrp.key</span>
            <span>📁 Speicherort: includes/db_config.enc</span>
            <span>🚫 Öffentlich gesperrt via .htaccess</span>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<!-- Save Bar -->
<div class="save-bar" id="saveBar">
  <span id="saveMsg"></span>
  <button onclick="saveSettings()" class="btn btn-primary">💾 Einstellungen speichern</button>
</div>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
// ── DB-Konfiguration ──────────────────────────────────────────────────────────
function toggleDbPw() {
  const i = document.getElementById('dbPass');
  i.type = i.type === 'password' ? 'text' : 'password';
}

async function testDbConnection() {
  const msg = document.getElementById('dbMsg');
  msg.style.display = 'block';
  msg.style.color = '#64748b';
  msg.textContent = 'Teste Verbindung...';

  const data = collectDbForm();
  const r = await fetch('api/admin_db.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...data, _test_only: true }),
  });
  const res = await r.json();
  if (res.ok) {
    msg.style.color = '#22c55e';
    msg.textContent = '✓ Verbunden – MariaDB ' + (res.db?.version || '');
  } else {
    msg.style.color = '#ef4444';
    msg.textContent = '✗ ' + (res.error || 'Fehler');
  }
}

function collectDbForm() {
  const f = document.getElementById('dbConfigForm');
  return {
    csrf_token: f.querySelector('[name=csrf_token]').value,
    host:    f.querySelector('[name=host]').value.trim(),
    port:    parseInt(f.querySelector('[name=port]').value) || 3306,
    name:    f.querySelector('[name=name]').value.trim(),
    user:    f.querySelector('[name=user]').value.trim(),
    pass:    f.querySelector('[name=pass]').value,
    charset: f.querySelector('[name=charset]').value,
  };
}

document.getElementById('dbConfigForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const msg = document.getElementById('dbMsg');
  msg.style.display = 'block';
  msg.style.color = '#64748b';
  msg.textContent = 'Speichere und teste...';

  const r = await fetch('api/admin_db.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(collectDbForm()),
  });
  const res = await r.json();
  if (res.ok) {
    msg.style.color = '#22c55e';
    msg.textContent = '✓ ' + res.message + ' (' + (res.db?.version || '') + ')';
  } else {
    msg.style.color = '#ef4444';
    msg.textContent = '✗ ' + (res.error || 'Fehler');
  }
});
// ── Einstellungen speichern ───────────────────────────────────────────────────
let changed = false;

document.querySelectorAll('[data-key]').forEach(el => {
  el.addEventListener('change', () => { changed = true; document.getElementById('saveBar').classList.add('visible'); });
  el.addEventListener('input',  () => { changed = true; document.getElementById('saveBar').classList.add('visible'); });
});

async function saveSettings() {
  const data = {};
  document.querySelectorAll('[data-key]').forEach(el => {
    const key = el.dataset.key;
    if (el.type === 'checkbox') data[key] = el.checked;
    else data[key] = el.value;
  });

  const r   = await fetch('api/admin_settings.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
  const res = await r.json();
  const msg = document.getElementById('saveMsg');
  if (res.ok) {
    msg.textContent = '✓ Gespeichert';
    msg.style.color = 'var(--green)';
    changed = false;
    setTimeout(() => { document.getElementById('saveBar').classList.remove('visible'); msg.textContent = ''; }, 2000);
  } else {
    msg.textContent = '✗ Fehler beim Speichern';
    msg.style.color = 'var(--red)';
  }
}

// ── FiveM Live-Daten ──────────────────────────────────────────────────────────
async function loadFiveMData() {
  const el = document.getElementById('fivemDataContent');
  el.innerHTML = '<span style="color:var(--text-2)">Lade Daten...</span>';

  try {
    const r = await fetch('api/players_status.php');
    const d = await r.json();
    document.getElementById('dataTimestamp').textContent = 'Abgerufen: ' + new Date().toLocaleTimeString('de-AT');
    renderData(d);
  } catch(e) {
    el.innerHTML = '<span style="color:var(--red)">Fehler beim Abrufen der Daten: ' + e.message + '</span>';
  }
}

function renderData(d) {
  const el = document.getElementById('fivemDataContent');

  if (!d.online) {
    el.innerHTML = '<span class="data-badge badge-offline">OFFLINE</span><p style="color:var(--text-2);margin-top:12px">Server ist nicht erreichbar.</p>';
    return;
  }

  const det = d.details || {};
  const pct = d.max_players > 0 ? Math.round(d.players / d.max_players * 100) : 0;

  const rows = [
    ['Status',              '<span class="data-badge badge-' + (d.online ? 'online' : 'offline') + '">' + (d.online ? 'ONLINE' : 'OFFLINE') + '</span>'],
    ['Spieler / Slots',     '<span class="data-val-large">' + d.players + '</span> / ' + d.max_players + ' <span style="color:var(--text-3);font-size:.8rem">(' + pct + '%)</span>'],
    ['Hostname',            d.hostname || '–'],
    ['Server-Name',         det.server_name       || '–'],
    ['Beschreibung',        det.description       || '–'],
    ['Map',                 det.map               || '–'],
    ['Gametype',            det.gametype          || '–'],
    ['Game',                det.gamename          || '–'],
    ['Game Build',          det.game_build        || '–'],
    ['Locale',              det.locale            || '–'],
    ['Tags',                det.tags              || '–'],
    ['OneSync',             det.onesync           || '–'],
    ['Pure Level',          det.pure_level !== '' ? det.pure_level : '–'],
    ['ScriptHook',          det.script_hook === true ? '✓ Erlaubt' : '✗ Gesperrt'],
    ['LAN-Modus',           det.lan_mode === true ? '✓ An' : '✗ Aus'],
    ['Enhanced Host',       det.enhanced_host ? '✓ Ja' : '✗ Nein'],
    ['Support Status',      det.support_status    || '–'],
    ['Ressourcen',          det.resource_count > 0 ? '<span class="data-val-large">' + det.resource_count + '</span> geladen' : '–'],
    ['Server Version',      det.server_version    || '–'],
    ['Build Nummer',        det.server_version_num > 0 ? det.server_version_num : '–'],
    ['Upvote Power',        det.upvote_power  > 0 ? det.upvote_power  : '–'],
    ['Burst Power',         det.burst_power   > 0 ? det.burst_power   : '–'],
    ['Steam Ticket',        det.request_steam     || '–'],
    ['Connect IPs',         (det.connect_endpoints || []).join(', ') || '–'],
    ['Lizenz-Token',        det.license_token     || '–'],
    ['Owner',               det.owner_name        || '–'],
    ['Owner Profil',        det.owner_profile ? '<a href="' + det.owner_profile + '" target="_blank" style="color:var(--gold)">' + det.owner_profile + '</a>' : '–'],
    ['Banner (Connecting)', det.banner_connecting ? '<a href="' + det.banner_connecting + '" target="_blank" style="color:var(--gold)">Link</a>' : '–'],
    ['Banner (Detail)',     det.banner_detail     ? '<a href="' + det.banner_detail     + '" target="_blank" style="color:var(--gold)">Link</a>' : '–'],
    ['Server-Icon',         det.icon_url ? '<img src="' + det.icon_url + '" style="width:48px;height:48px;border-radius:6px;margin-top:4px">' : '–'],
  ];

  // Einsatzkraefte
  let jobsHtml = '–';
  if (d.jobs && d.jobs.length) {
    const total = d.jobs.reduce((s,j) => s + j.count, 0);
    jobsHtml = '<table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:.85rem">'
      + '<tr style="color:#4b5563;font-size:.7rem"><th style="text-align:left;padding:2px 6px">Job</th><th style="text-align:right;padding:2px 6px">Spieler</th><th style="text-align:right;padding:2px 6px">Anteil</th></tr>';
    d.jobs.forEach(j => {
      const pct = total > 0 ? Math.round(j.count/total*100) : 0;
      jobsHtml += '<tr style="border-top:1px solid #1e1e28">'
        + '<td style="padding:5px 6px;color:#e2e8f0">' + (j.label || j.key) + '</td>'
        + '<td style="padding:5px 6px;text-align:right;font-family:monospace;color:#e8b86d"><b>' + j.count + '</b></td>'
        + '<td style="padding:5px 6px;text-align:right;color:#64748b">' + pct + '%</td>'
        + '</tr>';
    });
    jobsHtml += '</table>';
  }
  rows.push(['Einsatzkräfte gesamt (' + (d.jobs ? d.jobs.length : 0) + ' Jobs)', jobsHtml]);

  let html = '<table class="data-table"><thead><tr><th>Feld</th><th>Wert</th></tr></thead><tbody>';
  rows.forEach(([k, v]) => {
    if (v && v !== '–' && v !== '') {
      html += '<tr><td>' + k + '</td><td>' + v + '</td></tr>';
    }
  });
  html += '</tbody></table>';

  // Ressourcen-Liste
  if (det.resource_count > 0 && det.resources && det.resources.length) {
    html += '<div style="margin-top:20px"><div class="admin-card-title" style="margin-bottom:10px">📦 Geladene Ressourcen (' + det.resource_count + ')</div>';
    html += '<div class="res-list">';
    det.resources.forEach(r => { html += '<span class="res-tag">' + r + '</span>'; });
    html += '</div></div>';
  }

  // Custom Vars
  if (det.custom_vars && Object.keys(det.custom_vars).length) {
    html += '<div style="margin-top:20px"><div class="admin-card-title" style="margin-bottom:10px">🔧 Custom Server-Vars (' + Object.keys(det.custom_vars).length + ')</div>';
    html += '<table class="data-table"><thead><tr><th>Convar</th><th>Wert</th></tr></thead><tbody>';
    Object.entries(det.custom_vars).forEach(([k,v]) => {
      html += '<tr><td>' + k + '</td><td>' + (v || '–') + '</td></tr>';
    });
    html += '</tbody></table></div>';
  }

  el.innerHTML = html;
}

// Beim Laden direkt abrufen
loadFiveMData();

// ── Fraktionen-Verwaltung ──────────────────────────────────────────────────────
let frakData = [];

async function loadFraktionen() {
  const el = document.getElementById('frakList');
  try {
    const r = await fetch('api/fraktionen.php');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (!d.ok) {
      el.innerHTML = '<span style="color:var(--red)">Fehler: ' + escHtml(d.error) + '</span>';
      return;
    }
    frakData = d.fraktionen;
    renderFrakList();
  } catch(e) {
    el.innerHTML = '<span style="color:#fca5a5;font-size:.85rem">❌ Fraktionen konnten nicht geladen werden: ' + escHtml(e.message) + '</span>';
  }
}

function renderFrakList() {
  const el = document.getElementById('frakList');
  if (!frakData.length) { el.innerHTML = '<div style="color:var(--text-2);font-size:.9rem">Keine Fraktionen vorhanden.</div>'; return; }
  el.innerHTML = frakData.map(f => `
    <div style="display:flex;align-items:center;gap:14px;background:var(--bg-card);border:1px solid var(--border);border-left:3px solid ${escHtml(f.farbe)};border-radius:10px;padding:12px 16px" data-id="${f.id}">
      <span style="font-size:1.5rem;flex-shrink:0">${escHtml(f.emoji)}</span>
      <div style="flex:1;min-width:0">
        <div style="font-size:.9rem;font-weight:700;margin-bottom:2px">${escHtml(f.name)}</div>
        <div style="font-size:.72rem;color:${escHtml(f.farbe)};font-weight:700;letter-spacing:1px">${escHtml(f.typ)}</div>
      </div>
      <div style="flex-shrink:0;text-align:right">
        <div style="font-size:.78rem;color:var(--text-2);margin-bottom:4px">SLOTS: <strong>${escHtml(f.slots_label)}</strong></div>
        <div style="font-size:.72rem;padding:2px 8px;border-radius:999px;display:inline-block;${f.aktiv == 1 ? 'background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)' : 'background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)'}">${f.aktiv == 1 ? 'Sichtbar' : 'Versteckt'}</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <button onclick="openFrakModal(${f.id})" class="btn btn-outline" style="padding:5px 12px;font-size:.8rem">✏️</button>
        <button onclick="deleteFraktion(${f.id})" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:8px;padding:5px 10px;font-size:.8rem;cursor:pointer">🗑</button>
      </div>
    </div>
  `).join('');
}

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function openFrakModal(id) {
  const modal = document.getElementById('frakModal');
  modal.style.display = 'flex';
  document.getElementById('frakModalMsg').style.display = 'none';
  if (id) {
    const f = frakData.find(x => x.id == id);
    if (!f) return;
    document.getElementById('frakModalTitle').textContent = 'Fraktion bearbeiten';
    document.getElementById('frakId').value = f.id;
    document.getElementById('frakEmoji').value = f.emoji;
    document.getElementById('frakName').value = f.name;
    document.getElementById('frakTyp').value = f.typ;
    document.getElementById('frakFarbe').value = f.farbe;
    document.getElementById('frakBeschreibung').value = f.beschreibung;
    document.getElementById('frakSlots').value = f.slots_label;
    document.getElementById('frakAktiv').checked = f.aktiv == 1;
  } else {
    document.getElementById('frakModalTitle').textContent = 'Neue Fraktion';
    document.getElementById('frakId').value = '0';
    document.getElementById('frakEmoji').value = '';
    document.getElementById('frakName').value = '';
    document.getElementById('frakTyp').value = '';
    document.getElementById('frakFarbe').value = '#3b82f6';
    document.getElementById('frakBeschreibung').value = '';
    document.getElementById('frakSlots').value = 'Offen';
    document.getElementById('frakAktiv').checked = true;
  }
}

function closeFrakModal() {
  document.getElementById('frakModal').style.display = 'none';
}

async function saveFraktion() {
  const id = parseInt(document.getElementById('frakId').value);
  const payload = {
    emoji:        document.getElementById('frakEmoji').value.trim(),
    name:         document.getElementById('frakName').value.trim(),
    typ:          document.getElementById('frakTyp').value.trim(),
    farbe:        document.getElementById('frakFarbe').value,
    beschreibung: document.getElementById('frakBeschreibung').value.trim(),
    slots_label:  document.getElementById('frakSlots').value.trim(),
    aktiv:        document.getElementById('frakAktiv').checked,
  };
  const msg = document.getElementById('frakModalMsg');
  const btn = document.getElementById('frakSaveBtn');
  btn.disabled = true;
  msg.style.display = 'none';

  const url = id > 0 ? 'api/fraktionen.php?id=' + id : 'api/fraktionen.php';
  const method = id > 0 ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) {
      closeFrakModal();
      await loadFraktionen();
    } else {
      msg.style.display = 'block';
      msg.style.background = 'rgba(239,68,68,.15)';
      msg.style.color = '#f87171';
      msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
    }
  } catch(e) {
    msg.style.display = 'block';
    msg.style.background = 'rgba(239,68,68,.15)';
    msg.style.color = '#f87171';
    msg.textContent = 'Netzwerkfehler: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}

async function deleteFraktion(id) {
  if (!confirm('Fraktion wirklich löschen?')) return;
  const r = await fetch('api/fraktionen.php?id=' + id, { method: 'DELETE' });
  const d = await r.json();
  if (d.ok) await loadFraktionen();
  else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

// Modal schließen bei Klick außerhalb
document.getElementById('frakModal').addEventListener('click', function(e) {
  if (e.target === this) closeFrakModal();
});

loadFraktionen();

// ── Changelog-Verwaltung ─────────────────────────────────────────────────────
let clData = [];
const CL_TAG_META = <?= json_encode(CHANGELOG_TAGS, JSON_UNESCAPED_UNICODE) ?>;

async function loadChangelogs() {
  const el = document.getElementById('clList');
  try {
    const r = await fetch('api/changelogs.php?all=1');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (!d.ok) {
      el.innerHTML = '<span style="color:var(--red)">Fehler: ' + escHtml(d.error) + '</span>';
      return;
    }
    clData = d.changelogs;
    renderClList();
  } catch(e) {
    el.innerHTML = '<span style="color:#fca5a5;font-size:.85rem">❌ Changelog konnte nicht geladen werden: ' + escHtml(e.message) + '</span>';
  }
}

function clFormatDateTime(mysqlStr) {
  if (!mysqlStr) return '';
  const d = new Date(mysqlStr.replace(' ', 'T'));
  if (isNaN(d)) return mysqlStr;
  const p = n => String(n).padStart(2, '0');
  return `${p(d.getDate())}.${p(d.getMonth()+1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function clStatusBadge(c) {
  if (c.status === 'draft') {
    return `<span style="font-size:.72rem;padding:2px 8px;border-radius:999px;background:rgba(100,116,139,.15);color:#94a3b8;border:1px solid rgba(100,116,139,.3)">Entwurf</span>`;
  }
  if (c.status === 'scheduled') {
    return `<span style="font-size:.72rem;padding:2px 8px;border-radius:999px;background:rgba(124,58,237,.15);color:#c4b5fd;border:1px solid rgba(124,58,237,.3)">Geplant · ${escHtml(clFormatDateTime(c.visible_at))}</span>`;
  }
  return `<span style="font-size:.72rem;padding:2px 8px;border-radius:999px;background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)">Veröffentlicht</span>`;
}

function renderClList() {
  const el = document.getElementById('clList');
  if (!clData.length) { el.innerHTML = '<div style="color:var(--text-2);font-size:.9rem">Keine Changelog-Eintr&auml;ge vorhanden.</div>'; return; }
  el.innerHTML = clData.map(c => {
    const meta = CL_TAG_META[c.tag] || { label: c.tag, color: '#64748b' };
    return `
    <div style="display:flex;align-items:center;gap:14px;background:var(--bg-card);border:1px solid var(--border);border-left:3px solid ${escHtml(meta.color)};border-radius:10px;padding:12px 16px" data-id="${c.id}">
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
          <span style="font-size:.68rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:${escHtml(meta.color)}1a;color:${escHtml(meta.color)}">${escHtml(meta.label)}</span>
          ${c.version ? `<span style="font-family:'Orbitron',sans-serif;font-size:.75rem;color:var(--gold)">${escHtml(c.version)}</span>` : ''}
          <span style="font-size:.75rem;color:var(--text-3)">${escHtml(c.entry_date)}</span>
          ${c.discord_sent_at ? `<span title="Discord-Ankündigung verschickt am ${escHtml(clFormatDateTime(c.discord_sent_at))}" style="font-size:.78rem">📨</span>` : ''}
        </div>
        <div style="font-size:.9rem;font-weight:700">${escHtml(c.title)}</div>
      </div>
      <div style="flex-shrink:0;text-align:right">
        ${clStatusBadge(c)}
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <button onclick="openClModal(${c.id})" class="btn btn-outline" style="padding:5px 12px;font-size:.8rem">✏️</button>
        <button onclick="deleteChangelog(${c.id})" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:8px;padding:5px 10px;font-size:.8rem;cursor:pointer">🗑</button>
      </div>
    </div>
  `; }).join('');
}

function toDatetimeLocal(mysqlStr) {
  if (!mysqlStr) return '';
  return mysqlStr.replace(' ', 'T').slice(0, 16);
}

function openClModal(id) {
  const modal = document.getElementById('clModal');
  modal.style.display = 'flex';
  document.getElementById('clModalMsg').style.display = 'none';
  if (id) {
    const c = clData.find(x => x.id == id);
    if (!c) return;
    document.getElementById('clModalTitle').textContent = 'Eintrag bearbeiten';
    document.getElementById('clId').value = c.id;
    document.getElementById('clVersion').value = c.version;
    document.getElementById('clTitle').value = c.title;
    document.getElementById('clTag').value = c.tag;
    document.getElementById('clDate').value = c.entry_date;
    document.getElementById('clContent').value = c.content;
    document.getElementById('clPublished').checked = c.published == 1;
    document.getElementById('clVisibleAt').value = toDatetimeLocal(c.visible_at);
  } else {
    document.getElementById('clModalTitle').textContent = 'Neuer Eintrag';
    document.getElementById('clId').value = '0';
    document.getElementById('clVersion').value = '';
    document.getElementById('clTitle').value = '';
    document.getElementById('clTag').value = 'feature';
    document.getElementById('clDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('clContent').value = '';
    document.getElementById('clPublished').checked = true;
    document.getElementById('clVisibleAt').value = '';
  }
}

function closeClModal() {
  document.getElementById('clModal').style.display = 'none';
}

async function saveChangelog() {
  const id = parseInt(document.getElementById('clId').value);
  const payload = {
    version:    document.getElementById('clVersion').value.trim(),
    title:      document.getElementById('clTitle').value.trim(),
    tag:        document.getElementById('clTag').value,
    entry_date: document.getElementById('clDate').value,
    content:    document.getElementById('clContent').value.trim(),
    published:  document.getElementById('clPublished').checked,
    visible_at: document.getElementById('clVisibleAt').value,
  };
  const msg = document.getElementById('clModalMsg');
  const btn = document.getElementById('clSaveBtn');
  btn.disabled = true;
  msg.style.display = 'none';

  const url = id > 0 ? 'api/changelogs.php?id=' + id : 'api/changelogs.php';
  const method = id > 0 ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) {
      closeClModal();
      await loadChangelogs();
    } else {
      msg.style.display = 'block';
      msg.style.background = 'rgba(239,68,68,.15)';
      msg.style.color = '#f87171';
      msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
    }
  } catch(e) {
    msg.style.display = 'block';
    msg.style.background = 'rgba(239,68,68,.15)';
    msg.style.color = '#f87171';
    msg.textContent = 'Netzwerkfehler: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}

async function deleteChangelog(id) {
  if (!confirm('Changelog-Eintrag wirklich löschen?')) return;
  const r = await fetch('api/changelogs.php?id=' + id, { method: 'DELETE' });
  const d = await r.json();
  if (d.ok) await loadChangelogs();
  else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

async function processDueChangelogs() {
  const btn = document.getElementById('clProcessDueBtn');
  btn.disabled = true;
  const original = btn.textContent;
  btn.textContent = 'Sende...';
  try {
    const r = await fetch('api/changelogs.php?action=process_due', { method: 'POST' });
    const d = await r.json();
    if (d.ok) {
      btn.textContent = d.sent > 0 ? `✓ ${d.sent} gesendet` : '✓ Keine fällig';
      await loadChangelogs();
    } else {
      btn.textContent = '✗ Fehler';
    }
  } catch (e) {
    btn.textContent = '✗ Netzwerkfehler';
  } finally {
    setTimeout(() => { btn.textContent = original; btn.disabled = false; }, 2500);
  }
}

document.getElementById('clModal').addEventListener('click', function(e) {
  if (e.target === this) closeClModal();
});

loadChangelogs();
</script>
</body>
</html>