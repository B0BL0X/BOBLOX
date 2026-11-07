<?php
/* /WebServices/Friends.asmx */
require_once __DIR__ . '/../App_Code/WebService.php';

function friends_target($p)
{
    $other = db_row('SELECT id FROM users WHERE id = ?', array(ws_int($p, 'userId')));
    if (!$other) ws_fail('No such user.', 404);
    return (int)$other['id'];
}

function friends_result($status)
{
    if (is_string($status) && $status !== '' && $status[0] === '!') ws_fail(substr($status, 1));
    return array('status' => $status, 'requests' => friend_request_count(current_user_id()));
}

ws_run(array(
    'RequestFriendship' => array('handler' => function ($p) {
        $me = ws_user();
        return friends_result(friend_request($me['id'], friends_target($p)));
    }),
    'AcceptFriendship' => array('handler' => function ($p) {
        $me = ws_user();
        return friends_result(friend_accept($me['id'], friends_target($p)));
    }),
    'RemoveFriendship' => array('handler' => function ($p) {
        $me = ws_user();
        return friends_result(friend_remove($me['id'], friends_target($p)));
    }),
));
