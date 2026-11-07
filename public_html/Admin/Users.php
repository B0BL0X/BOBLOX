<?php
/* Admin: users (ban, admin rights, passwords, delete) */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_admin();

$q = trim(get_str('q'));
$filter = get_str('show');
if (!in_array($filter, array('admins', 'banned'), true)) $filter = '';
$page = max(1, get_int('p', 1));
$perPage = 20;

$where = array('1 = 1');
$params = array();
if ($q !== '') {
    $where[] = "username LIKE ? ESCAPE '\\'";
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($filter === 'admins') $where[] = 'is_admin = 1';
if ($filter === 'banned') $where[] = 'banned = 1';
$whereSql = implode(' AND ', $where);
$total = (int)db_value("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$users = db_all("SELECT u.*, (SELECT COUNT(*) FROM items i WHERE i.creator_id = u.id AND i.deleted = 0) AS item_count
                 FROM users u WHERE $whereSql ORDER BY u.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$owner = site_owner_id();

$link = function ($changes) use ($q, $filter) {
    return url('/Admin/Users.aspx?' . http_build_query(array_filter(array_merge(array('q' => $q, 'show' => $filter), $changes), 'strlen')));
};

page_header('Admin: Users', array('scripts' => array('/js/Admin.js')));
?>
<div id="AdminContainer">
  <?php admin_tabs('Users'); ?>
  <div class="Panel">
    <h4>Users (<?php echo $total; ?>)</h4>
    <div class="PanelContent">
      <form method="get" action="<?php echo h(url('/Admin/Users.aspx')); ?>" class="AdminSearch">
        <input type="text" name="q" class="TextBox" value="<?php echo h($q); ?>" maxlength="20" />
        <select name="show">
          <option value="">Everyone</option>
          <option value="admins"<?php echo $filter === 'admins' ? ' selected="selected"' : ''; ?>>Admins</option>
          <option value="banned"<?php echo $filter === 'banned' ? ' selected="selected"' : ''; ?>>Banned</option>
        </select>
        <input type="submit" class="Button" value="Search" />
      </form>
      <table width="100%" cellpadding="4" cellspacing="0" class="AdminTable">
        <tr><th width="52"></th><th align="left">User</th><th align="left">Joined</th><th align="left">Last seen</th><th>Items</th><th align="left">Actions</th></tr>
<?php foreach ($users as $u) {
    $id = (int)$u['id'];
    $protected = $id === (int)$me['id'] || $id === $owner;
    ?>
        <tr id="User_<?php echo $id; ?>" class="<?php echo (int)$u['banned'] === 1 ? 'BannedRow' : ''; ?>">
          <td><a href="<?php echo h(profile_url($id)); ?>"><?php echo avatar_image($u, 48); ?></a></td>
          <td>
            <a href="<?php echo h(profile_url($id)); ?>"><b><?php echo h($u['username']); ?></b></a>
            <?php if ((int)$u['is_admin'] === 1) echo '<span class="AdminTag">Admin</span>'; ?>
            <?php if ($id === $owner) echo '<span class="AdminTag OwnerTag">Owner</span>'; ?>
            <?php if ((int)$u['banned'] === 1) echo '<span class="BannedTag">Banned</span>' . ($u['ban_reason'] !== '' ? '<br /><span class="Faded">Reason: ' . h($u['ban_reason']) . '</span>' : ''); ?>
          </td>
          <td><?php echo h(format_date($u['created'])); ?></td>
          <td><?php echo is_online($u) ? '<span class="UserOnlineMessage">Online</span>' : h(time_ago($u['last_seen'])); ?></td>
          <td align="center"><?php echo (int)$u['item_count']; ?></td>
          <td class="AdminActions">
<?php if ($protected) { ?>
            <span class="Faded"><?php echo $id === (int)$me['id'] ? 'This is you' : 'Site owner'; ?></span>
<?php } else { ?>
<?php if ((int)$u['banned'] === 1) { ?>
            <a href="#" class="Button" onclick="BOBLOX.Admin.unban(<?php echo $id; ?>); return false;">Unban</a>
<?php } else { ?>
            <a href="#" class="Button" onclick="BOBLOX.Admin.ban(<?php echo $id; ?>, '<?php echo h(addslashes($u['username'])); ?>'); return false;">Ban</a>
<?php } ?>
            <a href="#" class="Button" onclick="BOBLOX.Admin.setAdmin(<?php echo $id; ?>, <?php echo (int)$u['is_admin'] === 1 ? 'false' : 'true'; ?>, '<?php echo h(addslashes($u['username'])); ?>'); return false;"><?php echo (int)$u['is_admin'] === 1 ? 'Remove Admin' : 'Make Admin'; ?></a>
            <a href="#" class="Button" onclick="BOBLOX.Admin.setPassword(<?php echo $id; ?>, '<?php echo h(addslashes($u['username'])); ?>'); return false;">New Password</a>
            <a href="#" class="Button DangerButton" onclick="BOBLOX.Admin.deleteUser(<?php echo $id; ?>, '<?php echo h(addslashes($u['username'])); ?>'); return false;">Delete</a>
<?php } ?>
          </td>
        </tr>
<?php } ?>
      </table>
<?php if (!$users) { ?>
      <p class="Faded">No users found.</p>
<?php } ?>
      <?php echo render_pager($page, $pages, function ($p) use ($link) { return $link(array('p' => $p)); }); ?>
    </div>
  </div>
</div>
<?php
page_footer();
