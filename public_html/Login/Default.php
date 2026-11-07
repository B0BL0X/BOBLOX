<?php
/* Login page */
require_once __DIR__ . '/../App_Code/Bootstrap.php';

$return = safe_return_url(isset($_REQUEST['ReturnUrl']) ? $_REQUEST['ReturnUrl'] : '', '/My/Home.aspx');
if (current_user()) redirect($return);

$error = '';
$username = '';
if (is_post()) {
    csrf_check_form();
    $username = trim(post_str('UserName'));
    $result = attempt_login($username, post_str('Password'));
    if (is_int($result)) {
        login_user($result);
        redirect($return);
    }
    $error = $result;
}

page_header('Login');
?>
<div id="FrameLogin" class="FormPage">
  <div class="Panel">
    <h4>Login to <?php echo h(SITE_NAME); ?></h4>
    <form method="post" action="<?php echo h(url('/Login/Default.aspx')); ?>" class="PanelContent">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="ReturnUrl" value="<?php echo h($return); ?>" />
<?php if ($error) { ?>
      <div class="validationWarningSmall"><?php echo h($error); ?></div>
<?php } ?>
      <table cellpadding="4">
        <tr><td class="Label"><label for="UserName">Character Name:</label></td><td><input id="UserName" name="UserName" type="text" class="TextBox" maxlength="20" value="<?php echo h($username); ?>" autofocus="autofocus" /></td></tr>
        <tr><td class="Label"><label for="Password">Password:</label></td><td><input id="Password" name="Password" type="password" class="TextBox" maxlength="200" /></td></tr>
        <tr><td></td><td><input type="submit" class="Button" value="Login" /></td></tr>
      </table>
<?php if (REGISTRATION_OPEN) { ?>
      <p>Not a member? <a href="<?php echo h(url('/Login/NewAccount.aspx')); ?>">Sign up for free!</a></p>
<?php } ?>
    </form>
  </div>
</div>
<?php
page_footer();
