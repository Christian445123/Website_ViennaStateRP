<?php
// Release-Termin: 01.08.2026, 20:00 Uhr (Europe/Vienna)
$releaseDt = new DateTime('2026-08-01 20:00:00', new DateTimeZone('Europe/Vienna'));
$nowDt     = new DateTime('now', new DateTimeZone('Europe/Vienna'));

if ($nowDt < $releaseDt):
?>
<div class="top-banner release-countdown" id="releaseCountdown">
  <span class="rc-label">🚀 ViennaStateRP Release: 01.08.2026, 20:00 Uhr</span>
  <span class="rc-timer">
    <span class="rc-unit"><span class="rc-num" id="rcDays">–</span><span class="rc-lbl">Tage</span></span>
    <span class="rc-sep">:</span>
    <span class="rc-unit"><span class="rc-num" id="rcHours">–</span><span class="rc-lbl">Std</span></span>
    <span class="rc-sep">:</span>
    <span class="rc-unit"><span class="rc-num" id="rcMinutes">–</span><span class="rc-lbl">Min</span></span>
    <span class="rc-sep">:</span>
    <span class="rc-unit"><span class="rc-num" id="rcSeconds">–</span><span class="rc-lbl">Sek</span></span>
  </span>
</div>
<script>
(function () {
  var target = new Date('2026-08-01T20:00:00+02:00').getTime();
  function tick() {
    var diff = target - Date.now();
    if (diff <= 0) {
      var el = document.getElementById('releaseCountdown');
      if (el) el.remove();
      if (window.vsrpRestackBanners) window.vsrpRestackBanners();
      return;
    }
    document.getElementById('rcDays').textContent    = Math.floor(diff / 86400000);
    document.getElementById('rcHours').textContent   = String(Math.floor(diff % 86400000 / 3600000)).padStart(2, '0');
    document.getElementById('rcMinutes').textContent = String(Math.floor(diff % 3600000 / 60000)).padStart(2, '0');
    document.getElementById('rcSeconds').textContent = String(Math.floor(diff % 60000 / 1000)).padStart(2, '0');
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php endif; ?>
