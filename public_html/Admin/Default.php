<?php
/* Admin dashboard */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
require_admin();

$stats = array(
    'Members' => (int)db_value('SELECT COUNT(*) FROM users'),
    'Online now' => (int)db_value('SELECT COUNT(*) FROM users WHERE last_seen > ?', array(time() - 300)),
    'New today' => (int)db_value('SELECT COUNT(*) FROM users WHERE created > ?', array(time() - 86400)),
    'Banned' => (int)db_value('SELECT COUNT(*) FROM users WHERE banned = 1'),
    'Catalog items' => (int)db_value('SELECT COUNT(*) FROM items WHERE deleted = 0'),
    'Uploads today' => (int)db_value('SELECT COUNT(*) FROM items WHERE creator_id <> 0 AND created > ?', array(time() - 86400)),
);
$newUsers = db_all('SELECT * FROM users ORDER BY id DESC LIMIT 6');
$uploads = db_all('SELECT i.*, u.username AS creator_name FROM items i LEFT JOIN users u ON u.id = i.creator_id WHERE i.creator_id <> 0 AND i.deleted = 0 ORDER BY i.id DESC LIMIT 8');
$log = db_all('SELECT l.*, u.username FROM admin_log l LEFT JOIN users u ON u.id = l.admin_id ORDER BY l.id DESC LIMIT 25');

page_header('Admin Panel', array('scripts' => array('/js/Admin.js')));
?>
<div id="AdminContainer">
  <?php admin_tabs('Default'); ?>
<?php if (maintenance_on()) { ?>
  <div class="AdminWarning">Maintenance mode is <b>ON</b>. Only admins can use the site. <a href="<?php echo h(url('/Admin/Site.aspx')); ?>">Change it</a></div>
<?php } ?>
  <div class="AdminStats">
<?php foreach ($stats as $label => $n) { ?>
    <div class="AdminStat"><div class="AdminStatValue"><?php echo $n; ?></div><div class="AdminStatLabel"><?php echo h($label); ?></div></div>
<?php } ?>
    <div style="clear:both"></div>
  </div>
  <div class="AdminColumns">
    <div class="Panel AdminColumnLeft">
      <h4>Newest Uploads</h4>
      <div class="PanelContent">
<?php if (!$uploads) { ?>
        <p class="Faded">Nobody has uploaded anything yet.</p>
<?php } ?>
        <div class="Assets">
<?php foreach ($uploads as $item) {
    echo item_tile($item, '<div class="AssetAction"><a href="#" class="Button" onclick="BOBLOX.Admin.deleteItem(this, ' . (int)$item['id'] . '); return false;">Delete</a></div>', 100);
} ?>
        </div>
        <div style="clear:both"></div>
        <div class="PanelFooter"><a href="<?php echo h(url('/Admin/Catalog.aspx?uploads=1')); ?>">See all uploads</a></div>
      </div>
    </div>
    <div class="Panel AdminColumnRight">
      <h4>Newest Members</h4>
      <div class="PanelContent">
        <table width="100%" cellpadding="3" cellspacing="0" class="AdminTable">
<?php foreach ($newUsers as $u) { ?>
          <tr>
            <td width="52"><a href="<?php echo h(profile_url($u['id'])); ?>"><?php echo avatar_image($u, 48); ?></a></td>
            <td><a href="<?php echo h(profile_url($u['id'])); ?>"><b><?php echo h($u['username']); ?></b></a><?php echo (int)$u['banned'] === 1 ? ' <span class="BannedTag">Banned</span>' : ''; ?><br /><span class="Faded">joined <?php echo h(time_ago($u['created'])); ?></span></td>
          </tr>
<?php } ?>
        </table>
        <div class="PanelFooter"><a href="<?php echo h(url('/Admin/Users.aspx')); ?>">Manage users</a></div>
      </div>
    </div>
    <div style="clear:both"></div>
  </div>
  <div class="Panel">
    <h4>Admin Log</h4>
    <div class="PanelContent">
<?php if (!$log) { ?>
      <p class="Faded">Nothing has happened yet.</p>
<?php } else { ?>
      <table width="100%" cellpadding="3" cellspacing="0" class="AdminTable">
<?php foreach ($log as $l) { ?>
        <tr><td width="150" class="Faded"><?php echo h(format_datetime($l['created'])); ?></td><td width="120"><b><?php echo h($l['username'] !== null ? $l['username'] : '[deleted]'); ?></b></td><td><?php echo h($l['action']); ?></td></tr>
<?php } ?>
      </table>
<?php } ?>
    </div>
  </div>
</div>
<?php
page_footer();
