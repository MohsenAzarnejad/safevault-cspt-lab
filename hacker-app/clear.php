<?php
/* ============================================================================
 *  EvilCorp C2 - wipe collected loot. The dashboard's "Clear loot" button
 *  POSTs here to delete the JSON store between demo runs.
 *  FOR SECURITY TRAINING ONLY.
 * ==========================================================================*/

header('Content-Type: application/json; charset=utf-8');

$file = sys_get_temp_dir() . '/evilcorp_loot.json';
if (is_file($file)) { @unlink($file); }

echo '{"ok":true}';
