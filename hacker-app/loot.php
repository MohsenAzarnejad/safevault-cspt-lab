<?php
/* ============================================================================
 *  EvilCorp C2 - JSON feed of collected loot (the dashboard polls this).
 *  FOR SECURITY TRAINING ONLY.
 * ==========================================================================*/

header('Content-Type: application/json; charset=utf-8');

$file = sys_get_temp_dir() . '/evilcorp_loot.json';
echo is_file($file) ? file_get_contents($file) : '[]';
