<?php
if (!function_exists('setting')) {
    require_once __DIR__ . '/site_settings.php';
}
$restartTimesArr = array_values(array_filter(array_map('trim', explode(',', (string) setting('restart_times')))));
?>
<?php if (!empty($restartTimesArr)): ?>
<div class="top-banner restart-banner" id="restartBanner" style="display:none" data-restart-times="<?= htmlspecialchars(json_encode($restartTimesArr)) ?>">
  <span class="restart-banner-icon">&#128260;</span>
  <span>Server-Neustart in <strong id="restartBannerMins">30</strong> Minuten!</span>
</div>
<?php endif; ?>
