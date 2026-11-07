<?php
/* /WebServices/Users.asmx */
require_once __DIR__ . '/../App_Code/WebService.php';

ws_run(array(
    'UpdateProfile' => array('handler' => function ($p) {
        $user = ws_user();
        $blurb = str_replace("\r\n", "\n", ws_string($p, 'blurb', ''));
        $discord = trim(ws_string($p, 'discord', ''));
        if (mb_strlen($blurb, 'UTF-8') > 1000) ws_fail('Your blurb can be at most 1000 characters long.');
        if ($discord !== '' && !preg_match('#^https://(www\.)?(discord\.com|discord\.gg|discordapp\.com)/[A-Za-z0-9/_\-.]+$#', $discord)) {
            ws_fail('The Discord link must start with https://discord.com/ or https://discord.gg/');
        }
        db_query('UPDATE users SET blurb = ?, discord = ? WHERE id = ?', array($blurb, $discord, (int)$user['id']));
        return array('saved' => true);
    }),
    'ChangePassword' => array('handler' => function ($p) {
        $user = ws_user();
        $old = ws_string($p, 'oldPassword');
        $new = ws_string($p, 'newPassword');
        if (!password_verify($old, $user['password_hash'])) ws_fail('Your current password is wrong.');
        if (strlen($new) < 6 || strlen($new) > 200) ws_fail('Passwords must be at least 6 characters long.');
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', array(password_hash($new, PASSWORD_DEFAULT), (int)$user['id']));
        return array('saved' => true);
    }),
    'Logout' => array('handler' => function ($p) {
        logout_user();
        return array('redirect' => url('/Default.aspx'));
    }),
));
