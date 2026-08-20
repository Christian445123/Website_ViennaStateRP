<?php
// ============================================================
//  ViennaStateRP – Cookie-Consent-Banner & Google Analytics
//  Lädt Google Analytics (gtag.js) erst nach ausdrücklicher
//  Zustimmung des Nutzers (Art. 6 Abs. 1 lit. a DSGVO).
// ============================================================
require_once __DIR__ . '/site_settings.php';

$gaEnabled = (bool) setting('ga_enabled');
$gaId      = trim((string) setting('ga_measurement_id'));
?>
<?php if ($gaEnabled && $gaId !== ''): ?>
<div class="cookie-banner" id="cookieBanner" role="dialog" aria-live="polite" aria-label="Cookie-Hinweis" hidden>
  <div class="cookie-banner-inner">
    <p class="cookie-banner-text">
      Wir würden gerne Google Analytics einsetzen, um die Nutzung dieser Website statistisch auszuwerten
      und sie zu verbessern. Dabei werden Cookies gesetzt und Daten an Google übertragen. Das ist freiwillig
      – ohne deine Zustimmung findet kein Tracking statt. Details findest du in unserer
      <a href="datenschutz.php">Datenschutzerklärung</a>.
    </p>
    <div class="cookie-banner-actions">
      <button type="button" class="btn btn-outline" id="cookieDecline">Ablehnen</button>
      <button type="button" class="btn btn-discord" id="cookieAccept">Akzeptieren</button>
    </div>
  </div>
</div>
<style>
  .cookie-banner {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 9999;
    background: var(--bg-card); border-top: 1px solid var(--border);
    padding: 20px 24px; box-shadow: 0 -8px 24px rgba(0,0,0,0.35);
  }
  .cookie-banner-inner {
    max-width: 1100px; margin: 0 auto; display: flex; gap: 24px;
    align-items: center; flex-wrap: wrap; justify-content: space-between;
  }
  .cookie-banner-text { color: var(--text-2); font-size: 0.9rem; line-height: 1.6; margin: 0; flex: 1 1 420px; }
  .cookie-banner-text a { color: var(--gold); }
  .cookie-banner-actions { display: flex; gap: 12px; flex: 0 0 auto; }
  @media (max-width: 640px) {
    .cookie-banner { padding: 16px; }
    .cookie-banner-actions { width: 100%; }
    .cookie-banner-actions .btn { flex: 1; }
  }
</style>
<script>
(function () {
  var GA_ID = <?= json_encode($gaId) ?>;
  var STORAGE_KEY = 'vsrp_cookie_consent';
  var banner = document.getElementById('cookieBanner');
  var acceptBtn = document.getElementById('cookieAccept');
  var declineBtn = document.getElementById('cookieDecline');

  function loadGA() {
    if (window.__vsrpGaLoaded) return;
    window.__vsrpGaLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', GA_ID);
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
    document.head.appendChild(s);
  }

  function getConsent() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }
  function setConsent(value) {
    try { localStorage.setItem(STORAGE_KEY, value); } catch (e) {}
  }
  function showBanner() { if (banner) banner.hidden = false; }
  function hideBanner() { if (banner) banner.hidden = true; }

  var consent = getConsent();
  if (consent === 'accepted') {
    loadGA();
  } else {
    if (consent === 'declined') window['ga-disable-' + GA_ID] = true;
    if (consent !== 'declined') showBanner();
  }

  if (acceptBtn) acceptBtn.addEventListener('click', function () {
    setConsent('accepted');
    window['ga-disable-' + GA_ID] = false;
    hideBanner();
    loadGA();
  });
  if (declineBtn) declineBtn.addEventListener('click', function () {
    setConsent('declined');
    window['ga-disable-' + GA_ID] = true;
    hideBanner();
  });

  // Aufrufbar über den "Cookie-Einstellungen"-Link im Footer, um die
  // Auswahl jederzeit zu widerrufen bzw. erneut zu treffen.
  window.vsrpOpenCookieSettings = function () {
    showBanner();
  };
})();
</script>
<?php endif; ?>
