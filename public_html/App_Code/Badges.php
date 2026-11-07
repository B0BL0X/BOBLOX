<?php
/*
 * Badges: automatic ones (earned by doing things, can be switched off in the admin panel)
 * and custom ones that admins make and award by hand.
 */

/** key => array(label, image path, description) */
function auto_badges()
{
    return array(
        'Administrator' => array('Administrator', 'img/bobloxadmin.png', 'This badge is given to administrators on the site.'),
        'Homestead' => array('Homestead', 'img/Homestead.webp', 'This badge is given to people who have made an item for the catalog.'),
        'Bricksmith' => array('Bricksmith', 'img/Supermod-0.webp', 'This badge is given to people who have made 5 or more catalog items.'),
        'Friendship' => array('Friendship', 'img/Ambassador-256x256.webp', 'This badge is given to people who have 5 or more friends.'),
        'Veteran' => array('Veteran', 'img/Badge_combat.webp', 'This badge is given to people who have been members for a year or more.'),
    );
}

function badge_image_url($badge)
{
    return url('/Asset/Badge.ashx?id=' . (int)$badge['id']);
}

/** Badges a user has: array of array(name, image url, description). */
function user_badges($user)
{
    $auto = auto_badges();
    $on = function ($key) { return setting('badge_auto_' . $key) === '1'; };
    $earned = array();
    if ((int)$user['is_admin'] === 1) $earned[] = 'Administrator';
    $created = (int)db_value('SELECT COUNT(*) FROM items WHERE creator_id = ? AND deleted = 0', array((int)$user['id']));
    if ($created >= 1) $earned[] = 'Homestead';
    if ($created >= 5) $earned[] = 'Bricksmith';
    if (friend_count($user['id']) >= 5) $earned[] = 'Friendship';
    if (time() - (int)$user['created'] >= 365 * 86400) $earned[] = 'Veteran';

    $badges = array();
    foreach ($earned as $key) {
        if ($on($key)) $badges[] = array($auto[$key][0], url('/' . $auto[$key][1]), $auto[$key][2]);
    }
    foreach (db_all('SELECT b.* FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ? ORDER BY ub.created', array((int)$user['id'])) as $b) {
        $badges[] = array($b['name'], badge_image_url($b), $b['description']);
    }
    return $badges;
}

/** Stores an uploaded badge picture (fit into 150x150). Returns array('file' => ...) or array('error' => ...). */
function store_badge_upload($upload)
{
    $stored = store_item_upload($upload, ITEM_TSHIRT); // same checks, fit into 256x256
    if (isset($stored['error'])) return $stored;
    $path = DATA_DIR . '/uploads/' . basename($stored['file']);
    $im = imagecreatefrompng($path);
    $dst = gd_fit($im, 150, 150, false);
    imagesavealpha($dst, true);
    imagepng($dst, $path, 9);
    imagedestroy($im);
    imagedestroy($dst);
    return $stored;
}
