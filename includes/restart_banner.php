<?php
if (!function_exists('setting')) {
    require_once __DIR__ . '/site_settings.php';
}
$restartTimesArr = array_values(array_filter(array_map('trim', explode(',', (string) setting('restart_times')))));
$manualRestartAt = (string) setting('manual_restart_at');
?>
<?php if (!empty($restartTimesArr) || $manualRestartAt !== ''): ?>
<div class="restart-banner" id="restartBanner" style="display:none"
     data-restart-times="<?= htmlspecialchars(json_encode($restartTimesArr)) ?>"
     data-manual-restart="<?= htmlspecialchars($manualRestartAt) ?>">
  <span class="restart-banner-icon">&#128260;</span>
  <span>Server-Neustart in <strong id="restartBannerCountdown">30:00</strong> Min.</span>
</div>
<?php endif; ?>
