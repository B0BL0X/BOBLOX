<?php
/* /WebServices/Admin.asmx - quick actions in the admin panel. Admins only. */
require_once __DIR__ . '/../App_Code/WebService.php';

function ws_admin()
{
    $u = ws_user();
    if ((int)$u['is_admin'] !== 1) ws_fail('Only administrators can do that.', 403);
    return $u;
}

function ws_target_user($p)
{
    $t = db_row('SELECT * FROM users WHERE id = ?', array(ws_int($p, 'userId')));
    if (!$t) ws_fail('No such user.', 404);
    $err = admin_target_error($t);
    if ($err) ws_fail($err, 403);
    return $t;
}

function ws_any_item($p)
{
    $item = db_row('SELECT * FROM items WHERE id = ?', array(ws_int($p, 'itemId')));
    if (!$item) ws_fail('No such item.', 404);
    return $item;
}

ws_run(array(
    // ---- users ----
    'BanUser' => array('handler' => function ($p) {
        ws_admin();
        $t = ws_target_user($p);
        $reason = trim(ws_string($p, 'reason', ''));
        if (mb_strlen($reason, 'UTF-8') > 200) ws_fail('Keep the reason under 200 characters.');
        admin_ban_user($t, $reason);
        return array('ok' => true);
    }),
    'UnbanUser' => array('handler' => function ($p) {
        ws_admin();
        admin_unban_user(ws_target_user($p));
        return array('ok' => true);
    }),
    'SetAdmin' => array('handler' => function ($p) {
        ws_admin();
        $t = ws_target_user($p);
        admin_set_admin($t, !empty($p['admin']));
        return array('ok' => true);
    }),
    'SetPassword' => array('handler' => function ($p) {
        ws_admin();
        $t = ws_target_user($p);
        $pw = ws_string($p, 'password');
        if (strlen($pw) < 6 || strlen($pw) > 200) ws_fail('Passwords must be at least 6 characters long.');
        admin_set_password($t, $pw);
        return array('ok' => true);
    }),
    'DeleteUser' => array('handler' => function ($p) {
        ws_admin();
        $t = ws_target_user($p);
        if (strcasecmp(ws_string($p, 'confirmName'), $t['username']) !== 0) ws_fail('The name you typed does not match.');
        admin_delete_user($t);
        return array('ok' => true);
    }),

    // ---- catalog ----
    'DeleteItem' => array('handler' => function ($p) {
        ws_admin();
        $item = ws_any_item($p);
        if ((int)$item['deleted'] === 0) {
            delete_item($item);
            admin_log('Deleted the item "' . $item['name'] . '" (#' . $item['id'] . ')');
        }
        return array('ok' => true);
    }),
    'RestoreItem' => array('handler' => function ($p) {
        ws_admin();
        $item = ws_any_item($p);
        db_query('UPDATE items SET deleted = 0 WHERE id = ?', array((int)$item['id']));
        admin_log('Restored the item "' . $item['name'] . '" (#' . $item['id'] . ')');
        return array('ok' => true);
    }),

    // ---- badges ----
    'AwardBadge' => array('handler' => function ($p) {
        $me = ws_admin();
        $badge = db_row('SELECT * FROM badges WHERE id = ?', array(ws_int($p, 'badgeId')));
        if (!$badge) ws_fail('No such badge.', 404);
        $t = db_row('SELECT * FROM users WHERE username = ?', array(trim(ws_string($p, 'username'))));
        if (!$t) ws_fail('There is no user with that name.', 404);
        db_query('INSERT OR IGNORE INTO user_badges (user_id, badge_id, awarded_by, created) VALUES (?, ?, ?, ?)', array((int)$t['id'], (int)$badge['id'], (int)$me['id'], time()));
        admin_log('Gave the badge "' . $badge['name'] . '" to ' . $t['username']);
        return array('ok' => true, 'userId' => (int)$t['id'], 'username' => $t['username']);
    }),
    'RevokeBadge' => array('handler' => function ($p) {
        ws_admin();
        $badge = db_row('SELECT * FROM badges WHERE id = ?', array(ws_int($p, 'badgeId')));
        $t = db_row('SELECT * FROM users WHERE id = ?', array(ws_int($p, 'userId')));
        if (!$badge || !$t) ws_fail('Not found.', 404);
        db_query('DELETE FROM user_badges WHERE user_id = ? AND badge_id = ?', array((int)$t['id'], (int)$badge['id']));
        admin_log('Took the badge "' . $badge['name'] . '" from ' . $t['username']);
        return array('ok' => true);
    }),
    'DeleteBadge' => array('handler' => function ($p) {
        ws_admin();
        $badge = db_row('SELECT * FROM badges WHERE id = ?', array(ws_int($p, 'badgeId')));
        if (!$badge) ws_fail('No such badge.', 404);
        db_query('DELETE FROM badges WHERE id = ?', array((int)$badge['id']));
        @unlink(DATA_DIR . '/uploads/' . basename($badge['file']));
        admin_log('Deleted the badge "' . $badge['name'] . '"');
        return array('ok' => true);
    }),
));
