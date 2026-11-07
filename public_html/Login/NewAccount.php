<?php
/* Sign up page */
require_once __DIR__ . '/../App_Code/Bootstrap.php';

if (current_user()) redirect(url('/My/Home.aspx'));

$errors = array();
$username = '';
if (registration_open() && is_post()) {
    csrf_check_form();
    $username = trim(post_str('UserName'));
    $password = post_str('Password');
    $err = username_error($username);
    if ($err) $errors[] = $err;
    if (strlen($password) < 6) $errors[] = 'Passwords must be at least 6 characters long.';
    if (strlen($password) > 200) $errors[] = 'That password is too long.';
    if ($password !== post_str('ConfirmPassword')) $errors[] = 'The passwords do not match.';
    if (strcasecmp($password, $username) === 0) $errors[] = 'Your password cannot be your username.';
    if (!post_str('AgreeTerms')) $errors[] = 'Please agree to be nice.';
    if (!$errors) {
        $id = create_account($username, $password);
        login_user($id);
        redirect(url('/My/Character.aspx?welcome=1'));
    }
}

page_header('Sign Up');
?>
<div id="FrameSignup" class="FormPage">
  <div class="Panel">
    <h4>Sign up and make your character</h4>
<?php if (!registration_open()) { ?>
    <div class="PanelContent"><p>Sign ups are closed right now. Please come back later!</p></div>
<?php } else { ?>
    <form method="post" action="<?php echo h(url('/Login/NewAccount.aspx')); ?>" class="PanelContent">
      <?php echo csrf_field(); ?>
<?php foreach ($errors as $e) { ?>
      <div class="validationWarningSmall"><?php echo h($e); ?></div>
<?php } ?>
      <table cellpadding="4">
        <tr><td class="Label"><label for="UserName">Character Name:</label></td><td><input id="UserName" name="UserName" type="text" class="TextBox" maxlength="20" value="<?php echo h($username); ?>" /><br /><span class="Hint">3-20 letters, numbers or _. This will be your profile address.</span></td></tr>
        <tr><td class="Label"><label for="Password">Password:</label></td><td><input id="Password" name="Password" type="password" class="TextBox" maxlength="200" /><br /><span class="Hint">At least 6 characters.</span></td></tr>
        <tr><td class="Label"><label for="ConfirmPassword">Confirm Password:</label></td><td><input id="ConfirmPassword" name="ConfirmPassword" type="password" class="TextBox" maxlength="200" /></td></tr>
        <tr><td></td><td><label><input type="checkbox" name="AgreeTerms" value="1" /> I will be nice and only upload things I am allowed to.</label></td></tr>
        <tr><td></td><td><input type="submit" class="Button" value="Sign Up" /></td></tr>
      </table>
      <p>Already a member? <a href="<?php echo h(url('/Login/Default.aspx')); ?>">Login</a></p>
    </form>
<?php } ?>
  </div>
</div>
<?php
page_footer();
