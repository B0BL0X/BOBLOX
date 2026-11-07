<?php
/*
 * Catalog items: Hats, Faces, T-Shirts, Shirts and Pants.
 */

function item_types()
{
    // type id => array(singular, plural, url slug)
    return array(
        ITEM_HAT => array('Hat', 'Hats', 'Hats'),
        ITEM_FACE => array('Face', 'Faces', 'Faces'),
        ITEM_TSHIRT => array('T-Shirt', 'T-Shirts', 'TShirts'),
        ITEM_SHIRT => array('Shirt', 'Shirts', 'Shirts'),
        ITEM_PANTS => array('Pants', 'Pants', 'Pants'),
    );
}

function item_type_name($type, $plural = false)
{
    $t = item_types();
    return isset($t[$type]) ? $t[$type][$plural ? 1 : 0] : 'Item';
}

function item_type_from_slug($slug)
{
    foreach (item_types() as $id => $t) {
        if (strcasecmp($t[2], $slug) === 0) return $id;
    }
    return null;
}

/** Types people can upload themselves. */
function uploadable_types()
{
    return array(ITEM_TSHIRT, ITEM_SHIRT, ITEM_PANTS, ITEM_FACE);
}

function get_item($id)
{
    return db_row('SELECT i.*, u.username AS creator_name FROM items i LEFT JOIN users u ON u.id = i.creator_id WHERE i.id = ? AND i.deleted = 0', array((int)$id));
}

function item_creator_name($item)
{
    return (int)$item['creator_id'] === 0 ? SITE_NAME : ($item['creator_name'] !== null ? $item['creator_name'] : '[deleted]');
}

function owns_item($userId, $itemId)
{
    return (bool)db_value('SELECT 1 FROM inventory WHERE user_id = ? AND item_id = ?', array((int)$userId, (int)$itemId));
}

/** Add a (free) item to someone's inventory. Returns false if they already had it. */
function take_item($userId, $item)
{
    $st = db_query('INSERT OR IGNORE INTO inventory (user_id, item_id, created) VALUES (?, ?, ?)', array((int)$userId, (int)$item['id'], time()));
    if ($st->rowCount() > 0) {
        db_query('UPDATE items SET takes = takes + 1 WHERE id = ?', array((int)$item['id']));
        return true;
    }
    return false;
}

/** Where an item's image lives on disk (null for hats / built-in faces). */
function item_file_path($item)
{
    if (empty($item['file'])) return null;
    if (strpos($item['file'], 'starter/') === 0) {
        $p = __DIR__ . '/../Asset/Starter/' . basename($item['file']);
    } else {
        $p = DATA_DIR . '/uploads/' . basename($item['file']);
    }
    return is_file($p) ? $p : null;
}

/** URL of the texture the 3D figure uses. */
function item_texture_url($item)
{
    if (empty($item['file'])) return null;
    return url('/Asset/Image.ashx?id=' . (int)$item['id']);
}

function item_thumb_url($item, $size = 110)
{
    return url('/Thumbs/Asset.ashx?id=' . (int)$item['id'] . '&x=' . (int)$size);
}

function item_url($item)
{
    return url('/Item.aspx?ID=' . (int)$item['id']);
}

/**
 * Validates an uploaded image and stores a cleaned PNG copy.
 * Returns array('file' => 'u/....png') or array('error' => '...').
 */
function store_item_upload($upload, $type)
{
    if (!is_array($upload) || !isset($upload['error']) || is_array($upload['error'])) {
        return array('error' => 'Please choose an image to upload.');
    }
    if ($upload['error'] === UPLOAD_ERR_NO_FILE) return array('error' => 'Please choose an image to upload.');
    if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE || $upload['size'] > MAX_UPLOAD_BYTES) {
        return array('error' => 'That image is too big. The limit is ' . round(MAX_UPLOAD_BYTES / 1048576, 1) . ' MB.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        return array('error' => 'The upload failed, please try again.');
    }
    $info = @getimagesize($upload['tmp_name']);
    if (!$info || !in_array($info[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF), true)) {
        return array('error' => 'Only PNG, JPG and GIF images are allowed.');
    }
    if ($info[0] > 4096 || $info[1] > 4096) {
        return array('error' => 'That image is too large (max 4096 x 4096 pixels).');
    }
    $src = @imagecreatefromstring(file_get_contents($upload['tmp_name']));
    if (!$src) return array('error' => 'That image could not be read.');

    // Target sizes: shirts & pants use the 585x559 template, faces 128x128, T-Shirts up to 256x256.
    if ($type === ITEM_SHIRT || $type === ITEM_PANTS) {
        $dst = gd_fit($src, 585, 559, true);
    } elseif ($type === ITEM_FACE) {
        $dst = gd_fit($src, 128, 128, false);
    } else {
        $dst = gd_fit($src, 256, 256, false);
    }
    imagedestroy($src);

    $dir = DATA_DIR . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(16)) . '.png';
    imagepng($dst, $dir . '/' . $name, 9);
    imagedestroy($dst);
    return array('file' => 'u/' . $name);
}

/** New transparent truecolor image. */
function gd_canvas($w, $h)
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    return $im;
}

/** Resize into a w x h canvas; $stretch fills it exactly, otherwise keeps the aspect ratio centred. */
function gd_fit($src, $w, $h, $stretch)
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $dst = gd_canvas($w, $h);
    imagealphablending($dst, false);
    if ($stretch) {
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    } else {
        $scale = min($w / $sw, $h / $sh);
        $nw = max(1, (int)round($sw * $scale));
        $nh = max(1, (int)round($sh * $scale));
        imagecopyresampled($dst, $src, (int)(($w - $nw) / 2), (int)(($h - $nh) / 2), 0, 0, $nw, $nh, $sw, $sh);
    }
    imagealphablending($dst, true);
    return $dst;
}

function create_item($creatorId, $type, $name, $description, $file)
{
    db_query('INSERT INTO items (type, name, description, creator_id, file, created) VALUES (?, ?, ?, ?, ?, ?)',
        array((int)$type, $name, $description, (int)$creatorId, $file, time()));
    $id = (int)db()->lastInsertId();
    take_item($creatorId, array('id' => $id));
    db_query('UPDATE items SET takes = 0 WHERE id = ?', array($id)); // the creator's own copy does not count
    return $id;
}

/** Soft-delete: removes it from the catalog and from everyone wearing it. */
function delete_item($item)
{
    $wearers = db_all('SELECT user_id FROM wearing WHERE item_id = ?', array((int)$item['id']));
    db_query('UPDATE items SET deleted = 1 WHERE id = ?', array((int)$item['id']));
    db_query('DELETE FROM wearing WHERE item_id = ?', array((int)$item['id']));
    db_query('DELETE FROM inventory WHERE item_id = ?', array((int)$item['id']));
    foreach ($wearers as $w) {
        avatar_invalidate((int)$w['user_id']);
    }
    foreach ((array)glob(DATA_DIR . '/thumbs/asset_' . (int)$item['id'] . '*.png') as $f) @unlink($f);
}

function can_manage_item($item)
{
    $u = current_user();
    if (!$u) return false;
    if ((int)$u['is_admin'] === 1) return true;
    return (int)$item['creator_id'] !== 0 && (int)$item['creator_id'] === (int)$u['id'];
}

/** Built-in face slot image cut from Asset/Figure/faces.png */
function face_slot_image($slot)
{
    $sheet = @imagecreatefrompng(__DIR__ . '/../Asset/Figure/faces.png');
    $im = gd_canvas(128, 128);
    if ($sheet) {
        imagealphablending($im, false);
        imagecopy($im, $sheet, 0, 0, 0, max(0, min(3, (int)$slot)) * 128, 128, 128);
        imagealphablending($im, true);
        imagedestroy($sheet);
    }
    return $im;
}
