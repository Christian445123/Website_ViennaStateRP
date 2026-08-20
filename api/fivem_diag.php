<?php
declare(strict_types=1);
// TEMPORAERES Diagnose-Script – nach dem Test loeschen!
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$url = defined('FIVEM_API_URL')
    ? FIVEM_API_URL
    : ('http://' . (defined('FIVEM_IP') ? FIVEM_IP : '?') . ':' . (defined('FIVEM_PORT') ? FIVEM_PORT : '?'));

echo "FIVEM_API_URL = " . $url . "\n";
echo "FIVEM_IP      = " . (defined('FIVEM_IP')   ? FIVEM_IP   : 'NOT DEFINED') . "\n";
echo "FIVEM_PORT    = " . (defined('FIVEM_PORT')  ? FIVEM_PORT : 'NOT DEFINED') . "\n\n";

// DNS check
$ip = gethostbyname(parse_url($url, PHP_URL_HOST) ?: '');
echo "DNS resolved  = " . $ip . "\n\n";

// Curl test
$endpoints = ['/dynamic.json', '/info.json', '/players.json'];
foreach ($endpoints as $path) {
    $full = rtrim($url, '/') . $path;
    $ch   = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'VSRP-Diag/1.0',
    ]);
    $result = curl_exec($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time   = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $error  = curl_error($ch);

    echo "GET $path\n";
    echo "  HTTP: $code  Time: {$time}ms\n";
    if ($error)  echo "  ERROR: $error\n";
    if ($result) echo "  BODY:  " . substr($result, 0, 200) . "\n";
    echo "\n";
}
