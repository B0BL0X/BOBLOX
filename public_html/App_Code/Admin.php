<?php
/*
 * Admin panel helpers: permissions, the admin log, user moderation and maintenance mode.
 */

/** Built-in hat shapes (keys of BOBLOX.Hats in js/Hats.js). */
function hat_meshes()
{
    return array(
        'tophat' => 'Top Hat', 'cone' => 'Traffic Cone', 'cap' => 'Baseball Cap', 'party' => 'Party Hat',
        'crown' => 'Crown', 'viking' => 'Viking Helmet', 'chef' => 'Chef Hat', 'halo' => 'Halo',
        'cowboy' => 'Cowboy Hat', 'beanie' => 'Beanie', 'bucket' => 'Bucket Hat', 'antlers' => 'Antlers',
    );
}

/** Shows a "no access" page to anyone who is not an admin. */
function require_admin()
{
    $u = require_login();
    if ((int)$u['is_admin'] !== 1) {
        http_response_code(403);
        page_header('Access Denied');
        echo '<div class="FormPage"><div class="Panel"><h4>Access Denied</h4><div class="PanelContent"><p>Only administrators can see this page.</p></div></div></div>';
        page_footer();
        exit;
    }
    return $u;
}

function admin_log($action)
{
    db_query('INSERT INTO admin_log (admin_id, action, created) VALUES (?, ?, ?)', array(current_user_id(), $action, time()));
}

/** The site owner: the oldest admin account. Other admins cannot ban, demote or delete it. */
function site_owner_id()
{
    return (int)db_value('SELECT id FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1');
}

/** Returns an error message if the current admin may not moderate $target. */
function admin_target_error($target)
{
    $me = current_user();
    if ((int)$target['id'] === (int)$me['id']) return 'You cannot do that to your own account.';
    if ((int)$target['id'] === site_owner_id()) return 'The site owner cannot be changed by other admins.';
    return null;
}

function admin_ban_user($target, $reason)
{
    db_query('UPDATE users SET banned = 1, ban_reason = ? WHERE id = ?', array($reason, (int)$target['id']));
    admin_log('Banned ' . $target['username'] . ($reason !== '' ? ' (' . $reason . ')' : ''));
}

function admin_unban_user($target)
{
    db_query("UPDATE users SET banned = 0, ban_reason = '' WHERE id = ?", array((int)$target['id']));
    admin_log('Unbanned ' . $target['username']);
}

function admin_set_admin($target, $on)
{
    db_query('UPDATE users SET is_admin = ? WHERE id = ?', array($on ? 1 : 0, (int)$target['id']));
    admin_log(($on ? 'Made ' : 'Removed admin from ') . $target['username'] . ($on ? ' an admin' : ''));
}

function admin_set_password($target, $password)
{
    db_query('UPDATE users SET password_hash = ? WHERE id = ?', array(password_hash($password, PASSWORD_DEFAULT), (int)$target['id']));
    admin_log('Set a new password for ' . $target['username']);
}

/** Deletes an account; their catalog items are removed from the catalog too. */
function admin_delete_user($target)
{
    foreach (db_all('SELECT * FROM items WHERE creator_id = ? AND deleted = 0', array((int)$target['id'])) as $item) {
        delete_item($item);
    }
    db_query('DELETE FROM users WHERE id = ?', array((int)$target['id']));
    foreach ((array)glob(thumbs_dir() . '/user_' . (int)$target['id'] . '*.png') as $f) @unlink($f);
    admin_log('Deleted the account ' . $target['username']);
}

/**
 * Maintenance mode: everyone except admins sees the maintenance page.
 * Logging in stays open so admins can get in; thumbnails and static files keep working.
 */
function maintenance_gate()
{
    if (!maintenance_on() || is_admin()) return;
    $entry = null;
    foreach (get_included_files() as $f) {
        if (basename($f) !== 'router.php') { $entry = $f; break; }
    }
    $root = realpath(__DIR__ . '/..');
    $rel = str_replace('\\', '/', substr(realpath($entry), strlen($root) + 1));
    $allowed = array('Login/Default.php', 'Login/index.php', 'Thumbs/Avatar.php', 'Thumbs/Asset.php', 'Asset/Image.php', 'Asset/Badge.php');
    if (in_array($rel, $allowed, true)) return;
    if ($rel === 'WebServices/Users.php' && isset($_GET['method']) && $_GET['method'] === 'Logout') return;

    http_response_code(503);
    header('Retry-After: 3600');
    if (strpos($rel, 'WebServices/') === 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('Message' => setting('maintenance_message'), 'ExceptionType' => 'Maintenance'));
        exit;
    }
    page_header('Maintenance', array('maintenance' => true));
    ?>
<div class="FormPage">
  <div class="SystemAlert MaintenanceNotice">
    <div class="SystemAlertText"><div class="Exclamation"></div>Down for maintenance</div>
  </div>
  <div class="Panel">
    <h4>We'll be right back!</h4>
    <div class="PanelContent MaintenanceBody">
      <img src="<?php echo h(url('/img/HardHatBullet.png')); ?>" alt="" class="Bullet" />
      <p><?php echo multiline(setting('maintenance_message')); ?></p>
      <p class="Faded" style="clear:both">Administrators can still <a href="<?php echo h(url('/Login/Default.aspx')); ?>">log in</a>.</p>
    </div>
  </div>
</div>
<?php
    page_footer();
    exit;
}

/** Tabs at the top of every admin page. */
function admin_tabs($current)
{
    $tabs = array('Default' => 'Dashboard', 'Catalog' => 'Catalog', 'Users' => 'Users', 'Badges' => 'Badges', 'Site' => 'Site & Maintenance');
    echo '<div class="CreateTabs AdminTabs">';
    foreach ($tabs as $page => $label) {
        echo '<a href="' . h(url('/Admin/' . $page . '.aspx')) . '" class="' . ($page === $current ? 'Selected' : '') . '">' . h($label) . '</a>';
    }
    echo '</div>';
}

/** Saves a 420x420 PNG rendered in the browser as an item's catalog picture (used for hats). */
function admin_store_item_thumbnail($itemId, $dataUrl)
{
    if (!is_string($dataUrl) || strpos($dataUrl, 'data:image/png;base64,') !== 0) return false;
    $bin = base64_decode(substr($dataUrl, 22), true);
    if ($bin === false || strlen($bin) > 3 * 1024 * 1024) return false;
    $info = @getimagesizefromstring($bin);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] !== 420 || $info[1] !== 420) return false;
    $im = @imagecreatefromstring($bin);
    if (!$im) return false;
    foreach ((array)glob(thumbs_dir() . '/asset_' . (int)$itemId . '*.png') as $f) @unlink($f);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagepng($im, thumbs_dir() . '/asset_' . (int)$itemId . '.png', 6);
    imagedestroy($im);
    return true;
}
