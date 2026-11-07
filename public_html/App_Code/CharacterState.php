<?php
/* Shared by My/Character.aspx and WebServices/Character.asmx */

/** What the page needs after any change. */
function character_state($user)
{
    $user = db_row('SELECT * FROM users WHERE id = ?', array((int)$user['id']));
    $wearing = array();
    foreach (avatar_wearing($user['id']) as $item) {
        $wearing[] = array(
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'type' => (int)$item['type'],
            'typeName' => item_type_name((int)$item['type']),
            'thumb' => item_thumb_url($item, 75),
            'url' => item_url($item),
        );
    }
    return array(
        'appearance' => avatar_appearance($user),
        'wearing' => $wearing,
        'thumbUrl' => avatar_thumb_url($user, 180),
        'thumbRendered' => (int)$user['thumb_rendered'] === 1,
    );
}
