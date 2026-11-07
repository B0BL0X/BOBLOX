<?php
/* /Asset/Badge.ashx?id=3 - picture of a custom badge (PNG). */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
session_write_close();

$badge = db_row('SELECT * FROM badges WHERE id = ?', array(get_int('id')));
$path = $badge ? DATA_DIR . '/uploads/' . basename($badge['file']) : null;
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit;
}
send_png($path, 86400);
