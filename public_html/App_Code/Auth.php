<?php
/*
 * Sessions, logins and CSRF protection.
 */

function auth_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('BOBLOXSESSION');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => BASE_PATH,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

/** The logged in user's row, or null. */
function current_user($reload = false)
{
    static $user = false;
    if ($user !== false && !$reload) return $user;
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $user = db_row('SELECT * FROM users WHERE id = ?', array((int)$_SESSION['user_id']));
        if (!$user) {
            unset($_SESSION['user_id']);
        } elseif (time() - (int)$user['last_seen'] > 60) {
            db_query('UPDATE users SET last_seen = ? WHERE id = ?', array(time(), $user['id']));
            $user['last_seen'] = time();
        }
    }
    return $user;
}

function current_user_id()
{
    $u = current_user();
    return $u ? (int)$u['id'] : 0;
}

function is_admin()
{
    $u = current_user();
    return $u && (int)$u['is_admin'] === 1;
}

/** Send visitors who are not logged in to the login page. */
function require_login()
{
    if (current_user()) return current_user();
    $here = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    redirect(url('/Login/Default.aspx') . (strpos(url('/Login/Default.aspx'), '?') === false ? '?' : '&') . 'ReturnUrl=' . rawurlencode($here));
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="__RequestVerificationToken" value="' . h(csrf_token()) . '" />';
}

function csrf_valid($token)
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** For classic form posts. */
function csrf_check_form()
{
    if (!csrf_valid(post_str('__RequestVerificationToken'))) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function username_error($name)
{
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $name)) {
        return 'Usernames must be 3-20 characters long and may only contain letters, numbers and _.';
    }
    if (preg_match('/^_|_$/', $name)) {
        return 'Usernames cannot start or end with _.';
    }
    $reserved = array('admin', 'administrator', 'moderator', 'boblox', 'my', 'login', 'asset', 'thumbs', 'img',
        'css', 'js', 'webservices', 'app_code', 'app_data', 'users', 'user', 'catalog', 'item', 'browse',
        'default', 'index', 'router', 'guest', 'system');
    if (in_array(strtolower($name), $reserved, true)) {
        return 'That username is reserved.';
    }
    if (db_value('SELECT 1 FROM users WHERE username = ?', array($name))) {
        return 'That username is already taken.';
    }
    return null;
}

function create_account($username, $password)
{
    $isFirst = (int)db_value('SELECT COUNT(*) FROM users') === 0;
    $c = default_body_colors();
    db_query('INSERT INTO users (username, password_hash, is_admin, created, last_seen, color_head, color_torso, color_left_arm, color_right_arm, color_left_leg, color_right_leg)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array(
        $username, password_hash($password, PASSWORD_DEFAULT), $isFirst ? 1 : 0, time(), time(),
        $c['Head'], $c['Torso'], $c['LeftArm'], $c['RightArm'], $c['LeftLeg'], $c['RightLeg'],
    ));
    $id = (int)db()->lastInsertId();
    // Everyone starts with the Smile face.
    $smile = db_value('SELECT id FROM items WHERE type = ? AND face_slot = 0 AND creator_id = 0 ORDER BY id LIMIT 1', array(ITEM_FACE));
    if ($smile) {
        db_query('INSERT OR IGNORE INTO inventory (user_id, item_id, created) VALUES (?, ?, ?)', array($id, $smile, time()));
    }
    return $id;
}

function login_user($userId)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    current_user(true);
}

function logout_user()
{
    $_SESSION = array();
    session_regenerate_id(true);
}

/** Returns the user id or an error message. Slows down repeated failures. */
function attempt_login($username, $password)
{
    $fails = isset($_SESSION['login_fails']) ? (int)$_SESSION['login_fails'] : 0;
    if ($fails >= 5) {
        usleep(min(5, $fails - 4) * 400000);
    }
    $user = db_row('SELECT id, password_hash FROM users WHERE username = ?', array($username));
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['login_fails'] = 0;
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db_query('UPDATE users SET password_hash = ? WHERE id = ?', array(password_hash($password, PASSWORD_DEFAULT), $user['id']));
        }
        return (int)$user['id'];
    }
    $_SESSION['login_fails'] = $fails + 1;
    return 'Wrong username or password.';
}

function is_online($user)
{
    return $user && time() - (int)$user['last_seen'] < 300;
}
