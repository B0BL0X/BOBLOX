<?php
/* Account settings: blurb, Discord link, password (saved over AJAX). */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_login();

page_header('Account Settings');
?>
<div class="FormPage">
  <div class="Panel">
    <h4>My Profile</h4>
    <div class="PanelContent">
      <table cellpadding="4" width="100%">
        <tr>
          <td class="Label" valign="top"><label for="Blurb">Blurb:</label></td>
          <td><textarea id="Blurb" rows="8" cols="60" maxlength="1000"><?php echo h($me['blurb']); ?></textarea><br /><span class="Hint">Tell people about yourself (1000 characters max). This shows on your profile.</span></td>
        </tr>
        <tr>
          <td class="Label"><label for="Discord">Discord link:</label></td>
          <td><input id="Discord" type="text" class="TextBox" size="60" maxlength="200" value="<?php echo h($me['discord']); ?>" /><br /><span class="Hint">e.g. https://discord.com/users/YOURUSERID (optional)</span></td>
        </tr>
        <tr>
          <td></td>
          <td><a href="#" class="Button" onclick="BOBLOX.Profile.save(); return false;">Save</a> <span id="ProfileStatus" class="StatusMessage"></span></td>
        </tr>
      </table>
    </div>
  </div>
  <div class="Panel">
    <h4>Change Password</h4>
    <div class="PanelContent">
      <table cellpadding="4">
        <tr><td class="Label"><label for="OldPassword">Current password:</label></td><td><input id="OldPassword" type="password" class="TextBox" maxlength="200" /></td></tr>
        <tr><td class="Label"><label for="NewPassword">New password:</label></td><td><input id="NewPassword" type="password" class="TextBox" maxlength="200" /></td></tr>
        <tr><td class="Label"><label for="ConfirmPassword">Confirm new password:</label></td><td><input id="ConfirmPassword" type="password" class="TextBox" maxlength="200" /></td></tr>
        <tr><td></td><td><a href="#" class="Button" onclick="BOBLOX.Profile.changePassword(); return false;">Change Password</a> <span id="PasswordStatus" class="StatusMessage"></span></td></tr>
      </table>
    </div>
  </div>
</div>
<?php
page_footer();
