<?php
/* Admin: welcome messages, site alert, sign ups and maintenance mode */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_admin();

$error = '';
if (is_post()) {
    csrf_check_form();
    $fields = array(
        'home_title' => array(trim(post_str('HomeTitle')), 100, 'The welcome post title'),
        'home_body' => array(trim(str_replace("\r\n", "\n", post_str('HomeBody'))), 4000, 'The welcome post'),
        'welcome_note' => array(trim(str_replace("\r\n", "\n", post_str('WelcomeNote'))), 1000, 'The new member welcome note'),
        'alert_text' => array(trim(post_str('AlertText')), 300, 'The site alert'),
        'maintenance_message' => array(trim(str_replace("\r\n", "\n", post_str('MaintenanceMessage'))), 1000, 'The maintenance message'),
    );
    foreach ($fields as $key => $f) {
        if (mb_strlen($f[0], 'UTF-8') > $f[1]) $error = $f[2] . ' can be at most ' . $f[1] . ' characters long.';
    }
    if ($fields['home_title'][0] === '') $error = 'The welcome post needs a title.';
    if (!$error) {
        $changes = array();
        foreach ($fields as $key => $f) {
            if ($f[0] !== setting($key)) { set_setting($key, $f[0]); $changes[] = $key; }
        }
        $reg = post_str('RegistrationOpen') ? '1' : '0';
        if ($reg !== setting('registration_open')) {
            set_setting('registration_open', $reg);
            admin_log($reg === '1' ? 'Opened sign ups' : 'Closed sign ups');
        }
        $maint = post_str('Maintenance') ? '1' : '0';
        if ($maint !== setting('maintenance')) {
            set_setting('maintenance', $maint);
            admin_log($maint === '1' ? 'Turned maintenance mode ON' : 'Turned maintenance mode OFF');
        }
        if ($changes) admin_log('Changed site settings: ' . implode(', ', $changes));
        redirect(url('/Admin/Site.aspx?saved=1'));
    }
}

page_header('Admin: Site', array('scripts' => array('/js/Admin.js')));
$val = function ($post, $key) { return is_post() ? post_str($post) : setting($key); };
?>
<div id="AdminContainer">
  <?php admin_tabs('Site'); ?>
<?php if ($error) { ?>
  <div class="validationWarningSmall"><?php echo h($error); ?></div>
<?php } ?>
<?php if (get_int('saved')) { ?>
  <div class="AdminNotice">Saved!</div>
<?php } ?>
  <form method="post" action="<?php echo h(url('/Admin/Site.aspx')); ?>">
    <?php echo csrf_field(); ?>
    <div class="Panel">
      <h4>Maintenance Mode</h4>
      <div class="PanelContent">
        <label class="BigToggle<?php echo maintenance_on() ? ' On' : ''; ?>"><input type="checkbox" name="Maintenance" value="1"<?php echo maintenance_on() ? ' checked="checked"' : ''; ?> /> Turn on maintenance mode</label>
        <p class="Hint">While it is on, everyone except admins sees the message below instead of the site. Admins can still log in and use everything.</p>
        <table cellpadding="4" width="100%">
          <tr><td class="Label" valign="top" width="170"><label for="MaintenanceMessage">Maintenance message:</label></td><td><textarea id="MaintenanceMessage" name="MaintenanceMessage" rows="3" cols="70" maxlength="1000"><?php echo h($val('MaintenanceMessage', 'maintenance_message')); ?></textarea></td></tr>
        </table>
      </div>
    </div>
    <div class="Panel">
      <h4>Welcome Messages</h4>
      <div class="PanelContent">
        <table cellpadding="4" width="100%">
          <tr><td class="Label" width="170"><label for="HomeTitle">Home page post title:</label></td><td><input id="HomeTitle" name="HomeTitle" type="text" class="TextBox" size="60" maxlength="100" value="<?php echo h($val('HomeTitle', 'home_title')); ?>" /></td></tr>
          <tr><td class="Label" valign="top"><label for="HomeBody">Home page post:</label></td><td><textarea id="HomeBody" name="HomeBody" rows="7" cols="70" maxlength="4000"><?php echo h($val('HomeBody', 'home_body')); ?></textarea><br /><span class="Hint">The "<?php echo h(SITE_NAME); ?> Info" post everyone sees on the home page.</span></td></tr>
          <tr><td class="Label" valign="top"><label for="WelcomeNote">New member welcome:</label></td><td><textarea id="WelcomeNote" name="WelcomeNote" rows="3" cols="70" maxlength="1000"><?php echo h($val('WelcomeNote', 'welcome_note')); ?></textarea><br /><span class="Hint">Shown on the character page right after someone signs up. {name} becomes their username.</span></td></tr>
        </table>
      </div>
    </div>
    <div class="Panel">
      <h4>Site Alert &amp; Sign Ups</h4>
      <div class="PanelContent">
        <table cellpadding="4" width="100%">
          <tr><td class="Label" width="170"><label for="AlertText">Site alert:</label></td><td><input id="AlertText" name="AlertText" type="text" class="TextBox" size="70" maxlength="300" value="<?php echo h($val('AlertText', 'alert_text')); ?>" /><br /><span class="Hint">A banner under the menu on every page, e.g. "New hats in the catalog!". Leave empty to hide it.</span></td></tr>
          <tr><td class="Label">Sign ups:</td><td><label><input type="checkbox" name="RegistrationOpen" value="1"<?php echo registration_open() ? ' checked="checked"' : ''; ?> /> Let new people make accounts</label></td></tr>
        </table>
      </div>
    </div>
    <div style="text-align:center"><input type="submit" class="Button SaveAllButton" value="Save Settings" /></div>
  </form>
</div>
<?php
page_footer();
