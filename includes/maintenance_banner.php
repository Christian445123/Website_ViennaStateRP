<?php
if (!function_exists('setting')) {
    require_once __DIR__ . '/site_settings.php';
}
if (setting('maintenance_mode')):
    $maintenanceBannerMsg = (string) setting('maintenance_message');
?>
<div class="top-banner maintenance-banner">
  <span class="maintenance-banner-icon">&#9888;&#65039;</span>
  <span><strong>FiveM-Server in Wartung:</strong> <?= htmlspecialchars($maintenanceBannerMsg) ?></span>
</div>
<?php endif; ?>
