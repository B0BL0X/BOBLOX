<?php
/* /WebServices/Character.asmx - everything the character page does over AJAX. */
require_once __DIR__ . '/../App_Code/WebService.php';
require_once __DIR__ . '/../App_Code/CharacterState.php';

function wardrobe_html($user, $type, $page)
{
    $perPage = 8;
    $total = (int)db_value('SELECT COUNT(*) FROM inventory v JOIN items i ON i.id = v.item_id WHERE v.user_id = ? AND i.type = ? AND i.deleted = 0', array((int)$user['id'], $type));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, $page));
    $items = db_all('SELECT i.*, u.username AS creator_name, (w.item_id IS NOT NULL) AS worn
                     FROM inventory v JOIN items i ON i.id = v.item_id
                     LEFT JOIN users u ON u.id = i.creator_id
                     LEFT JOIN wearing w ON w.item_id = i.id AND w.user_id = v.user_id
                     WHERE v.user_id = ? AND i.type = ? AND i.deleted = 0
                     ORDER BY v.created DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), array((int)$user['id'], $type));
    if (!$items) {
        return '<p class="EmptyWardrobe">You do not have any ' . h(item_type_name($type, true)) . ' yet.<br /><a href="' . h(url('/Catalog.aspx?c=' . item_types()[$type][2])) . '">Get some from the catalog!</a></p>';
    }
    $html = '<div class="Assets">';
    foreach ($items as $item) {
        if ($item['worn']) {
            $action = '<div class="AssetAction"><a href="#" class="Button" onclick="BOBLOX.Character.remove(' . (int)$item['id'] . '); return false;">Remove</a></div>';
        } else {
            $action = '<div class="AssetAction"><a href="#" class="Button" onclick="BOBLOX.Character.wear(' . (int)$item['id'] . '); return false;">Wear</a></div>';
        }
        $html .= item_tile($item, $action, 100);
    }
    $html .= '</div><div style="clear:both"></div>';
    if ($pages > 1) {
        $html .= '<div class="Pager">';
        if ($page > 1) $html .= '<a href="#" onclick="BOBLOX.Character.showWardrobe(' . $type . ', ' . ($page - 1) . '); return false;">&laquo; Previous</a> ';
        $html .= 'Page ' . $page . ' of ' . $pages;
        if ($page < $pages) $html .= ' <a href="#" onclick="BOBLOX.Character.showWardrobe(' . $type . ', ' . ($page + 1) . '); return false;">Next &raquo;</a>';
        $html .= '</div>';
    }
    return $html;
}

ws_run(array(
    'GetAppearance' => array('get' => true, 'handler' => function ($p) {
        $user = db_row('SELECT * FROM users WHERE id = ?', array(ws_int($p, 'userId')));
        if (!$user) ws_fail('No such user.', 404);
        return character_state($user);
    }),
    'SetBodyColor' => array('handler' => function ($p) {
        $user = ws_user();
        $err = set_body_color($user, ws_string($p, 'part'), ws_int($p, 'colorId'));
        if ($err) ws_fail($err);
        return character_state($user);
    }),
    'SetAllBodyColors' => array('handler' => function ($p) {
        $user = ws_user();
        $color = ws_int($p, 'colorId');
        foreach (array_keys(body_parts()) as $part) {
            $err = set_body_color($user, $part, $color);
            if ($err) ws_fail($err);
        }
        return character_state($user);
    }),
    'WearItem' => array('handler' => function ($p) {
        $user = ws_user();
        $item = get_item(ws_int($p, 'itemId'));
        if (!$item) ws_fail('That item does not exist any more.', 404);
        $err = wear_item($user, $item);
        if ($err) ws_fail($err);
        return character_state($user);
    }),
    'RemoveItem' => array('handler' => function ($p) {
        $user = ws_user();
        remove_item($user, ws_int($p, 'itemId'));
        return character_state($user);
    }),
    'GetWardrobe' => array('handler' => function ($p) {
        $user = ws_user();
        $type = ws_int($p, 'type');
        if (!isset(item_types()[$type])) ws_fail('Unknown item type.');
        return array('html' => wardrobe_html($user, $type, isset($p['page']) ? (int)$p['page'] : 1));
    }),
    'UploadThumbnail' => array('handler' => function ($p) {
        $user = ws_user();
        $err = avatar_store_thumbnail($user, ws_string($p, 'png'));
        if ($err) ws_fail($err);
        $user = current_user(true);
        return array('thumbUrl' => avatar_thumb_url($user, 180));
    }),
));
