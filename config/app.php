<?php
// Real credentials go in config/local.php (git-ignored), never here.
$cfg = ['env' => 'local', 'db_host' => '127.0.0.1', 'db_port' => 3307, 'db_name' => 'mss_fleet_management', 'db_user' => 'root', 'db_pass' => '', 'session_timeout' => 1800];
if (is_file(__DIR__ . '/local.php'))
    $cfg = array_replace($cfg, require __DIR__ . '/local.php');
return $cfg;
