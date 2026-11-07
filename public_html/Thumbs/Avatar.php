<?php
/* /Thumbs/Avatar.ashx?userId=1&x=150 - a user's character picture (PNG). */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
session_write_close();

$user = db_row('SELECT * FROM users WHERE id = ?', array(get_int('userId')));
if (!$user) {
    http_response_code(404);
    exit;
}
$path = avatar_thumb_file($user);
send_png(thumb_scaled($path, get_int('x', 150)), get_int('v') ? 86400 * 30 : 60);
