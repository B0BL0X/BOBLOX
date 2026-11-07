<?php
/*
 * Characters: body colours, worn items and thumbnails.
 *
 * Thumbnails work like the 2006 site: every profile shows a rendered PNG.
 *  - The character page renders the real 3D figure in the browser and uploads a
 *    420x420 PNG (WebServices/Character.asmx/UploadThumbnail).
 *  - Until that happens (new accounts, browsers without WebGL) the server draws a
 *    simple version with GD (avatar_render_fallback below).
 */

function thumbs_dir()
{
    $d = DATA_DIR . '/thumbs';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

function avatar_colors($user)
{
    $out = array();
    foreach (body_parts() as $part => $col) {
        $out[$part] = (int)$user[$col];
    }
    return $out;
}

/** Items the user is wearing, keyed by type. */
function avatar_wearing($userId)
{
    $rows = db_all('SELECT i.* FROM wearing w JOIN items i ON i.id = w.item_id WHERE w.user_id = ? AND i.deleted = 0 ORDER BY i.type', array((int)$userId));
    $out = array();
    foreach ($rows as $r) {
        $out[(int)$r['type']] = $r;
    }
    return $out;
}

/** Everything Avatar3D.js needs to draw a user. */
function avatar_appearance($user)
{
    $wearing = avatar_wearing($user['id']);
    $a = array(
        'colors' => avatar_colors($user),
        'face' => array('slot' => 0),
        'tshirt' => null,
        'shirt' => null,
        'pants' => null,
        'hat' => null,
    );
    if (isset($wearing[ITEM_FACE])) {
        $f = $wearing[ITEM_FACE];
        $a['face'] = $f['file'] ? array('image' => item_texture_url($f)) : array('slot' => (int)$f['face_slot']);
    }
    if (isset($wearing[ITEM_TSHIRT])) $a['tshirt'] = item_texture_url($wearing[ITEM_TSHIRT]);
    if (isset($wearing[ITEM_SHIRT])) $a['shirt'] = item_texture_url($wearing[ITEM_SHIRT]);
    if (isset($wearing[ITEM_PANTS])) $a['pants'] = item_texture_url($wearing[ITEM_PANTS]);
    if (isset($wearing[ITEM_HAT])) $a['hat'] = array('mesh' => $wearing[ITEM_HAT]['mesh'], 'color' => $wearing[ITEM_HAT]['color']);
    return $a;
}

/** Called whenever someone's look changes: drops cached thumbnails. */
function avatar_invalidate($userId)
{
    foreach ((array)glob(thumbs_dir() . '/user_' . (int)$userId . '*.png') as $f) {
        @unlink($f);
    }
    db_query('UPDATE users SET thumb_version = thumb_version + 1, thumb_rendered = 0 WHERE id = ?', array((int)$userId));
}

function avatar_thumb_url($user, $size = 150)
{
    return url('/Thumbs/Avatar.ashx?userId=' . (int)$user['id'] . '&x=' . (int)$size . '&v=' . (int)$user['thumb_version']);
}

function wear_item($user, $item)
{
    if (!owns_item($user['id'], $item['id'])) {
        return 'You do not own that item. Get it from the catalog first.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    db_query('DELETE FROM wearing WHERE user_id = ? AND item_id IN (SELECT id FROM items WHERE type = ?)', array((int)$user['id'], (int)$item['type']));
    db_query('INSERT OR IGNORE INTO wearing (user_id, item_id) VALUES (?, ?)', array((int)$user['id'], (int)$item['id']));
    $pdo->commit();
    avatar_invalidate($user['id']);
    return null;
}

function remove_item($user, $itemId)
{
    db_query('DELETE FROM wearing WHERE user_id = ? AND item_id = ?', array((int)$user['id'], (int)$itemId));
    avatar_invalidate($user['id']);
}

function set_body_color($user, $part, $colorId)
{
    $parts = body_parts();
    if (!isset($parts[$part])) return 'Unknown body part.';
    if (!brick_color_valid($colorId)) return 'Unknown colour.';
    db_query('UPDATE users SET ' . $parts[$part] . ' = ? WHERE id = ?', array($colorId, (int)$user['id']));
    avatar_invalidate($user['id']);
    return null;
}

/** Stores a PNG rendered by the browser. Returns null or an error. */
function avatar_store_thumbnail($user, $dataUrl)
{
    if (!is_string($dataUrl) || strpos($dataUrl, 'data:image/png;base64,') !== 0) return 'Bad image.';
    $bin = base64_decode(substr($dataUrl, 22), true);
    if ($bin === false || strlen($bin) > 3 * 1024 * 1024) return 'Bad image.';
    $info = @getimagesizefromstring($bin);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] !== 420 || $info[1] !== 420) return 'Thumbnails must be 420x420 PNG images.';
    $im = @imagecreatefromstring($bin);
    if (!$im) return 'Bad image.';
    foreach ((array)glob(thumbs_dir() . '/user_' . (int)$user['id'] . '*.png') as $f) @unlink($f);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagepng($im, thumbs_dir() . '/user_' . (int)$user['id'] . '.png', 6);
    imagedestroy($im);
    db_query('UPDATE users SET thumb_version = thumb_version + 1, thumb_rendered = 1 WHERE id = ?', array((int)$user['id']));
    return null;
}

/** Path of the 420x420 avatar PNG, drawing the fallback if needed. */
function avatar_thumb_file($user)
{
    $path = thumbs_dir() . '/user_' . (int)$user['id'] . '.png';
    if (!is_file($path)) {
        $wearing = avatar_wearing($user['id']);
        $im = avatar_render_fallback(avatar_colors($user), $wearing);
        imagepng($im, $path, 6);
        imagedestroy($im);
    }
    return $path;
}

/** Scaled copy of a square PNG, cached next to it. */
function thumb_scaled($path, $size)
{
    $sizes = array(48, 75, 100, 110, 150, 180, 250, 420);
    if (!in_array($size, $sizes, true)) $size = 150;
    if ($size === 420) return $path;
    $scaled = preg_replace('/\.png$/', '_' . $size . '.png', $path);
    if (!is_file($scaled) || filemtime($scaled) < filemtime($path)) {
        $src = imagecreatefrompng($path);
        $dst = gd_canvas($size, $size);
        imagealphablending($dst, false);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
        imagesavealpha($dst, true);
        imagepng($dst, $scaled, 6);
        imagedestroy($src);
        imagedestroy($dst);
    }
    return $scaled;
}

function send_png($path, $maxAge)
{
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=' . (int)$maxAge);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
    readfile($path);
    exit;
}

// ---------------------------------------------------------------------------
// GD fallback renderer: an oblique drawing of the figure's boxes.
// ---------------------------------------------------------------------------

function gd_hex($im, $hex, $factor = 1.0, $alpha = 0)
{
    $r = hexdec(substr($hex, 1, 2)) * $factor;
    $g = hexdec(substr($hex, 3, 2)) * $factor;
    $b = hexdec(substr($hex, 5, 2)) * $factor;
    return imagecolorallocatealpha($im, (int)min(255, $r), (int)min(255, $g), (int)min(255, $b), $alpha);
}

/** Average colour of a template region, or null if it is mostly transparent. */
function gd_region_color($src, $rect)
{
    $one = gd_canvas(1, 1);
    imagealphablending($one, false);
    imagecopyresampled($one, $src, 0, 0, $rect[0], $rect[1], 1, 1, $rect[2], $rect[3]);
    $c = imagecolorat($one, 0, 0);
    imagedestroy($one);
    $a = ($c >> 24) & 0x7F;
    if ($a > 90) return null;
    return sprintf('#%02x%02x%02x', ($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF);
}

function item_image_resource($item)
{
    $p = item_file_path($item);
    return $p ? @imagecreatefrompng($p) : null;
}

/**
 * $colors  = array('Head' => 6, ...)
 * $wearing = avatar_wearing() result (items keyed by type)
 */
function avatar_render_fallback($colors, $wearing, $size = 420)
{
    $im = gd_canvas($size, $size);
    $s = $size / 6.4;              // pixels per stud-unit
    $k = 0.38;                      // oblique depth factor
    $ox = $size / 2 - 0.25 * $s;    // screen x of x=0
    $oy = $size - 0.35 * $s;        // screen y of y=0

    $shirt = isset($wearing[ITEM_SHIRT]) ? item_image_resource($wearing[ITEM_SHIRT]) : null;
    $pants = isset($wearing[ITEM_PANTS]) ? item_image_resource($wearing[ITEM_PANTS]) : null;
    $tshirt = isset($wearing[ITEM_TSHIRT]) ? item_image_resource($wearing[ITEM_TSHIRT]) : null;
    if (isset($wearing[ITEM_FACE]) && $wearing[ITEM_FACE]['file']) {
        $face = item_image_resource($wearing[ITEM_FACE]);
    } else {
        $face = face_slot_image(isset($wearing[ITEM_FACE]) ? (int)$wearing[ITEM_FACE]['face_slot'] : 0);
    }

    $P = function ($x, $y, $z) use ($ox, $oy, $s, $k) {
        $d = 0.5 - $z;
        return array($ox + ($x + $k * $d) * $s, $oy - ($y + $k * 0.8 * $d) * $s);
    };

    // name, box (x0,y0,z0,x1,y1,z1), clothing layers (image, front rect, side rect, top rect)
    $L = array(
        'RightLeg' => array(array(-1.0, 0.03, -0.5, 0.0, 2.02, 0.5), array(array($pants, array(217, 355, 64, 128), array(19, 355, 64, 128), array(217, 289, 64, 64)))),
        'LeftLeg' => array(array(0.0, 0.03, -0.5, 1.0, 2.02, 0.5), array(array($pants, array(308, 355, 64, 128), array(374, 355, 64, 128), array(308, 289, 64, 64)))),
        'RightArm' => array(array(-2.0, 2.02, -0.5, -1.0, 4.02, 0.5), array(array($shirt, array(217, 355, 64, 128), array(19, 355, 64, 128), array(217, 289, 64, 64)))),
        'Torso' => array(array(-1.0, 2.02, -0.5, 1.0, 4.02, 0.5), array(
            array($pants, array(231, 74, 128, 128), array(361, 74, 64, 128), array(231, 8, 128, 64)),
            array($shirt, array(231, 74, 128, 128), array(361, 74, 64, 128), array(231, 8, 128, 64)))),
        'LeftArm' => array(array(1.0, 2.02, -0.5, 2.0, 4.02, 0.5), array(array($shirt, array(308, 355, 64, 128), array(374, 355, 64, 128), array(308, 289, 64, 64)))),
        'Head' => array(array(-0.6, 4.02, -0.6, 0.6, 5.2, 0.6), array()),
    );

    foreach ($L as $part => $def) {
        list($b, $layers) = $def;
        $hex = brick_color_hex(isset($colors[$part]) ? (int)$colors[$part] : 2);
        $topHex = $hex;
        $sideHex = $hex;
        foreach ($layers as $layer) {
            if (!$layer[0]) continue;
            $c = gd_region_color($layer[0], $layer[2]);
            if ($c) $sideHex = $c;
            $c = gd_region_color($layer[0], $layer[3]);
            if ($c) $topHex = $c;
        }
        list($x0, $y0, $z0, $x1, $y1, $z1) = $b;
        // top face
        $pts = array_merge($P($x0, $y1, $z1), $P($x1, $y1, $z1), $P($x1, $y1, $z0), $P($x0, $y1, $z0));
        imagefilledpolygon($im, $pts, gd_hex($im, $topHex, 1.12));
        // right side face (+x)
        $pts = array_merge($P($x1, $y1, $z1), $P($x1, $y1, $z0), $P($x1, $y0, $z0), $P($x1, $y0, $z1));
        imagefilledpolygon($im, $pts, gd_hex($im, $sideHex, 0.72));
        // front face
        $a = $P($x0, $y1, $z1);
        $c = $P($x1, $y0, $z1);
        $fx = (int)round($a[0]);
        $fy = (int)round($a[1]);
        $fw = (int)round($c[0] - $a[0]);
        $fh = (int)round($c[1] - $a[1]);
        imagefilledrectangle($im, $fx, $fy, $fx + $fw - 1, $fy + $fh - 1, gd_hex($im, $hex, 0.95));
        foreach ($layers as $layer) {
            if ($layer[0]) {
                $r = $layer[1];
                imagecopyresampled($im, $layer[0], $fx, $fy, $r[0], $r[1], $fw, $fh, $r[2], $r[3]);
            }
        }
        if ($part === 'Torso' && $tshirt) {
            imagecopyresampled($im, $tshirt, $fx, $fy, 0, 0, $fw, $fh, imagesx($tshirt), imagesy($tshirt));
        }
        if ($part === 'Head' && $face) {
            imagecopyresampled($im, $face, $fx, $fy, 0, 0, $fw, $fh, imagesx($face), imagesy($face));
        }
        // outline the front face lightly
        imagerectangle($im, $fx, $fy, $fx + $fw - 1, $fy + $fh - 1, gd_hex($im, $hex, 0.6, 80));
    }

    foreach (array($shirt, $pants, $tshirt, $face) as $r) {
        if ($r) imagedestroy($r);
    }
    imagealphablending($im, false);
    imagesavealpha($im, true);
    return $im;
}
