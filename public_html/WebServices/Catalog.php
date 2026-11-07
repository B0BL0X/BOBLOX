<?php
/* /WebServices/Catalog.asmx */
require_once __DIR__ . '/../App_Code/WebService.php';

ws_run(array(
    'TakeItem' => array('handler' => function ($p) {
        $user = ws_user();
        $item = get_item(ws_int($p, 'itemId'));
        if (!$item) ws_fail('That item does not exist any more.', 404);
        take_item($user['id'], $item);
        $item = get_item($item['id']);
        return array('owned' => true, 'takes' => (int)$item['takes']);
    }),
    'TakeAndWearItem' => array('handler' => function ($p) {
        $user = ws_user();
        $item = get_item(ws_int($p, 'itemId'));
        if (!$item) ws_fail('That item does not exist any more.', 404);
        take_item($user['id'], $item);
        $err = wear_item($user, $item);
        if ($err) ws_fail($err);
        return array('owned' => true, 'wearing' => true);
    }),
    'DeleteItem' => array('handler' => function ($p) {
        ws_user();
        $item = get_item(ws_int($p, 'itemId'));
        if (!$item) ws_fail('That item does not exist any more.', 404);
        if (!can_manage_item($item)) ws_fail('You are not allowed to delete that item.', 403);
        delete_item($item);
        return array('deleted' => true, 'redirect' => url('/Catalog.aspx'));
    }),
));
