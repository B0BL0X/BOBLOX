<?php
/* /Asset/Image.ashx?id=5 - the texture of a T-Shirt / Shirt / Pants / Face item (PNG). */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
session_write_close();

$item = db_row('SELECT * FROM items WHERE id = ? AND deleted = 0', array(get_int('id')));
$path = $item ? item_file_path($item) : null;
if (!$path) {
    http_response_code(404);
    exit;
}
send_png($path, 86400);
