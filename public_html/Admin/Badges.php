<?php
/* Admin: badges (switch automatic badges on/off, make custom badges and award them) */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_admin();

$error = '';
$notice = get_int('saved') ? 'Saved!' : '';
if (is_post()) {
    csrf_check_form();
    $action = post_str('action');
    if ($action === 'auto') {
        $on = isset($_POST['Auto']) && is_array($_POST['Auto']) ? $_POST['Auto'] : array();
        foreach (auto_badges() as $key => $b) {
            set_setting('badge_auto_' . $key, isset($on[$key]) ? '1' : '0');
        }
        admin_log('Changed which automatic badges are given out');
        redirect(url('/Admin/Badges.aspx?saved=1'));
    } elseif ($action === 'create' || $action === 'edit') {
        $name = trim(post_str('Name'));
        $description = trim(post_str('Description'));
        $badge = $action === 'edit' ? db_row('SELECT * FROM badges WHERE id = ?', array((int)post_str('ID'))) : null;
        $hasFile = isset($_FILES['Image']) && is_array($_FILES['Image']) && $_FILES['Image']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($name === '' || mb_strlen($name, 'UTF-8') > 40) {
            $error = 'Badge names must be 1-40 characters long.';
        } elseif (mb_strlen($description, 'UTF-8') > 200) {
            $error = 'Keep the description under 200 characters.';
        } elseif ($action === 'edit' && !$badge) {
            $error = 'That badge does not exist.';
        } elseif ($action === 'create' && !$hasFile) {
            $error = 'Please choose a picture for the badge.';
        } else {
            $file = $badge ? $badge['file'] : null;
            if ($hasFile) {
                $stored = store_badge_upload($_FILES['Image']);
                if (isset($stored['error'])) $error = $stored['error'];
                else $file = $stored['file'];
            }
            if (!$error) {
                if ($badge) {
                    if ($file !== $badge['file']) @unlink(DATA_DIR . '/uploads/' . basename($badge['file']));
                    db_query('UPDATE badges SET name = ?, description = ?, file = ? WHERE id = ?', array($name, $description, $file, (int)$badge['id']));
                    admin_log('Edited the badge "' . $name . '"');
                } else {
                    db_query('INSERT INTO badges (name, description, file, created) VALUES (?, ?, ?, ?)', array($name, $description, $file, time()));
                    admin_log('Made the badge "' . $name . '"');
                }
                redirect(url('/Admin/Badges.aspx?saved=1'));
            }
        }
    }
}

$badges = db_all('SELECT b.*, (SELECT COUNT(*) FROM user_badges ub WHERE ub.badge_id = b.id) AS holders FROM badges b ORDER BY b.id');
$editBadge = get_int('edit') ? db_row('SELECT * FROM badges WHERE id = ?', array(get_int('edit'))) : null;

page_header('Admin: Badges', array('scripts' => array('/js/Admin.js')));
?>
<div id="AdminContainer">
  <?php admin_tabs('Badges'); ?>
<?php if ($error) { ?>
  <div class="validationWarningSmall"><?php echo h($error); ?></div>
<?php } ?>
<?php if ($notice) { ?>
  <div class="AdminNotice"><?php echo h($notice); ?></div>
<?php } ?>
  <div class="Panel">
    <h4>Automatic Badges</h4>
    <div class="PanelContent">
      <p>These are given out by themselves. Untick one to hide it from every profile.</p>
      <form method="post" action="<?php echo h(url('/Admin/Badges.aspx')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="auto" />
<?php foreach (auto_badges() as $key => $b) { ?>
        <label class="AutoBadge">
          <img src="<?php echo h(url('/' . $b[1])); ?>" width="60" height="60" alt="" /><br />
          <input type="checkbox" name="Auto[<?php echo h($key); ?>]" value="1"<?php echo setting('badge_auto_' . $key) === '1' ? ' checked="checked"' : ''; ?> />
          <b><?php echo h($b[0]); ?></b><br /><span class="Hint"><?php echo h($b[2]); ?></span>
        </label>
<?php } ?>
        <div style="clear:both;padding-top:6px"><input type="submit" class="Button" value="Save" /></div>
      </form>
    </div>
  </div>

  <div class="Panel">
    <h4>Custom Badges</h4>
    <div class="PanelContent">
<?php if (!$badges) { ?>
      <p class="Faded">You have not made any badges yet. Make one below, then give it to people.</p>
<?php } ?>
<?php foreach ($badges as $b) {
    $holders = db_all('SELECT u.id, u.username FROM user_badges ub JOIN users u ON u.id = ub.user_id WHERE ub.badge_id = ? ORDER BY ub.created DESC', array((int)$b['id']));
    ?>
      <div class="CustomBadge" id="Badge_<?php echo (int)$b['id']; ?>">
        <img src="<?php echo h(badge_image_url($b)); ?>" width="75" height="75" alt="" class="CustomBadgeImage" />
        <div class="CustomBadgeInfo">
          <b><?php echo h($b['name']); ?></b> <span class="Faded">(<?php echo (int)$b['holders'] === 1 ? '1 person' : (int)$b['holders'] . ' people'; ?>)</span><br />
          <span class="Hint"><?php echo h($b['description']); ?></span>
          <div class="BadgeAward">
            <input type="text" class="TextBox" id="Award_<?php echo (int)$b['id']; ?>" maxlength="20" placeholder="username" />
            <a href="#" class="Button" onclick="BOBLOX.Admin.awardBadge(<?php echo (int)$b['id']; ?>); return false;">Give badge</a>
            <a class="Button" href="<?php echo h(url('/Admin/Badges.aspx?edit=' . (int)$b['id'])); ?>#BadgeForm">Edit</a>
            <a href="#" class="Button DangerButton" onclick="BOBLOX.Admin.deleteBadge(<?php echo (int)$b['id']; ?>, this); return false;">Delete</a>
          </div>
          <div class="BadgeHolders" id="Holders_<?php echo (int)$b['id']; ?>">
<?php foreach ($holders as $hld) { ?>
            <span class="BadgeHolder" id="Holder_<?php echo (int)$b['id']; ?>_<?php echo (int)$hld['id']; ?>"><a href="<?php echo h(profile_url($hld['id'])); ?>"><?php echo h($hld['username']); ?></a> <a href="#" title="Take the badge away" onclick="BOBLOX.Admin.revokeBadge(<?php echo (int)$b['id']; ?>, <?php echo (int)$hld['id']; ?>); return false;">[x]</a></span>
<?php } ?>
          </div>
        </div>
        <div style="clear:both"></div>
      </div>
<?php } ?>
    </div>
  </div>

  <div class="Panel" id="BadgeForm">
    <h4><?php echo $editBadge ? 'Edit "' . h($editBadge['name']) . '"' : 'Make a New Badge'; ?></h4>
    <div class="PanelContent">
      <form method="post" enctype="multipart/form-data" action="<?php echo h(url('/Admin/Badges.aspx' . ($editBadge ? '?edit=' . (int)$editBadge['id'] : ''))); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editBadge ? 'edit' : 'create'; ?>" />
        <input type="hidden" name="ID" value="<?php echo $editBadge ? (int)$editBadge['id'] : 0; ?>" />
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int)MAX_UPLOAD_BYTES; ?>" />
        <table cellpadding="4">
          <tr><td class="Label"><label for="Name">Name:</label></td><td><input id="Name" name="Name" type="text" class="TextBox" size="30" maxlength="40" value="<?php echo h($editBadge ? $editBadge['name'] : post_str('Name')); ?>" /></td></tr>
          <tr><td class="Label"><label for="Description">Description:</label></td><td><input id="Description" name="Description" type="text" class="TextBox" size="60" maxlength="200" value="<?php echo h($editBadge ? $editBadge['description'] : post_str('Description')); ?>" /><br /><span class="Hint">Shown when people point at the badge, e.g. "This badge is given to people who won the building contest."</span></td></tr>
          <tr><td class="Label"><label for="Image">Picture:</label></td><td><input id="Image" name="Image" type="file" accept="image/png,image/jpeg,image/gif" /><?php if ($editBadge) echo ' <span class="Hint">(leave empty to keep the current one)</span>'; ?><br /><span class="Hint">A square PNG with a transparent background looks best.</span></td></tr>
          <tr><td></td><td><input type="submit" class="Button" value="<?php echo $editBadge ? 'Save Badge' : 'Make Badge'; ?>" /><?php if ($editBadge) echo ' <a href="' . h(url('/Admin/Badges.aspx')) . '">Cancel</a>'; ?></td></tr>
        </table>
      </form>
    </div>
  </div>
</div>
<?php
page_footer();
