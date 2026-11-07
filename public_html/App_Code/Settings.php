<?php
/*
 * Site settings changed from the admin panel (stored in the settings table).
 * Config.php gives the starting values.
 */

function setting_defaults()
{
    return array(
        'home_title' => HOME_POST_TITLE,
        'home_body' => HOME_POST_BODY,
        'welcome_note' => 'Welcome to ' . SITE_NAME . ', {name}! Click a body part to change its colour, then pick a hat, some clothes and a pose. Everything saves by itself.',
        'alert_text' => '',
        'registration_open' => REGISTRATION_OPEN ? '1' : '0',
        'maintenance' => '0',
        'maintenance_message' => SITE_NAME . ' is being worked on right now. Please come back soon!',
        'badge_auto_Administrator' => '1',
        'badge_auto_Homestead' => '1',
        'badge_auto_Bricksmith' => '1',
        'badge_auto_Friendship' => '1',
        'badge_auto_Veteran' => '1',
    );
}

function setting($key)
{
    static $cache = null;
    if ($key === null) { $cache = null; return null; } // reset
    if ($cache === null) {
        $cache = setting_defaults();
        foreach (db_all('SELECT key, value FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    return isset($cache[$key]) ? $cache[$key] : null;
}

function set_setting($key, $value)
{
    db_query('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)', array($key, (string)$value));
    setting(null);
}

function registration_open()
{
    return setting('registration_open') === '1';
}

function maintenance_on()
{
    return setting('maintenance') === '1';
}
