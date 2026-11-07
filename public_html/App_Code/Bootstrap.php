<?php
/*
 * Included at the top of every page (our "Global.asax").
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Html.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/BrickColors.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Items.php';
require_once __DIR__ . '/Avatar.php';
require_once __DIR__ . '/Poses.php';
require_once __DIR__ . '/Friends.php';
require_once __DIR__ . '/Badges.php';
require_once __DIR__ . '/Admin.php';
require_once __DIR__ . '/Layout.php';

if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0775, true);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

auth_start_session();
maintenance_gate();
