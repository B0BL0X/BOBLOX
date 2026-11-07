<?php
/* /Thumbs/Asset.ashx?id=5&x=110 - catalog picture of an item (PNG). */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
session_write_close();

$item = db_row('SELECT * FROM items WHERE id = ? AND deleted = 0', array(get_int('id')));
if (!$item) {
    http_response_code(404);
    exit;
}
$size = get_int('x', 110);
$type = (int)$item['type'];

if ($type === ITEM_HAT) {
    // pre-rendered hat pictures (tools/render-hats.js)
    $mesh = preg_replace('/[^a-z]/', '', (string)$item['mesh']);
    $color = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$item['color']));
    $p = __DIR__ . '/../img/Hats/' . $mesh . '_' . $color . '.png';
    if (!is_file($p)) $p = __DIR__ . '/../img/Hats/' . $mesh . '.png';
    if (!is_file($p)) $p = __DIR__ . '/../img/Hats/unknown.png';
    send_png($p, 86400);
}

$path = thumbs_dir() . '/asset_' . (int)$item['id'] . '.png';
if (!is_file($path)) {
    if ($type === ITEM_FACE) {
        // the face on a plain yellow head
        $im = gd_canvas(420, 420);
        $head = gd_hex($im, brick_color_hex(6));
        imagefilledellipse($im, 210, 210, 360, 360, $head);
        $face = $item['file'] ? item_image_resource($item) : face_slot_image((int)$item['face_slot']);
        if ($face) {
            imagecopyresampled($im, $face, 50, 50, 0, 0, 320, 320, imagesx($face), imagesy($face));
            imagedestroy($face);
        }
    } else {
        // a grey figure wearing the clothing
        $grey = array();
        foreach (array_keys(body_parts()) as $part) $grey[$part] = 2;
        $im = avatar_render_fallback($grey, array($type => $item));
    }
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagepng($im, $path, 6);
    imagedestroy($im);
}
send_png(thumb_scaled($path, $size), 3600);
