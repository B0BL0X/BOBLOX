<?php
/* Upload a T-Shirt, Shirt, Pants or Face (a classic multipart form post). */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_login();

$type = (int)(is_post() ? post_str('Type') : get_int('type', ITEM_TSHIRT));
if (!in_array($type, uploadable_types(), true)) $type = ITEM_TSHIRT;
$name = '';
$description = '';
$error = '';

if (is_post()) {
    csrf_check_form();
    $name = trim(post_str('Name'));
    $description = trim(str_replace("\r\n", "\n", post_str('Description')));
    $recent = (int)db_value('SELECT COUNT(*) FROM items WHERE creator_id = ? AND created > ?', array((int)$me['id'], time() - 3600));
    if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
        $error = 'Please give it a name (up to 50 characters).';
    } elseif (mb_strlen($description, 'UTF-8') > 500) {
        $error = 'The description can be at most 500 characters long.';
    } elseif ($recent >= 20 && !is_admin()) {
        $error = 'You have made a lot of items in the last hour. Take a break and try again later.';
    } else {
        $stored = store_item_upload(isset($_FILES['Image']) ? $_FILES['Image'] : null, $type);
        if (isset($stored['error'])) {
            $error = $stored['error'];
        } else {
            $id = create_item($me['id'], $type, $name, $description, $stored['file']);
            redirect(url('/Item.aspx?ID=' . $id));
        }
    }
}

$help = array(
    ITEM_TSHIRT => 'A T-Shirt is a picture that goes on the front of your torso. Any square-ish image works best (it will be fit into 256 x 256).',
    ITEM_SHIRT => 'A Shirt wraps around your torso and arms. Download the template, paint inside the boxes, and upload it (585 x 559).',
    ITEM_PANTS => 'Pants wrap around your torso and legs. Download the template, paint inside the boxes, and upload it (585 x 559).',
    ITEM_FACE => 'A Face goes on the front of your head. Use a transparent PNG so your head colour shows through (fit into 128 x 128).',
);

page_header('Create');
?>
<div class="FormPage">
  <div class="Panel">
    <h4>Create a <?php echo h(item_type_name($type)); ?></h4>
    <div class="PanelContent">
      <div class="CreateTabs">
<?php foreach (uploadable_types() as $t) { ?>
        <a href="<?php echo h(url('/My/Create.aspx?type=' . $t)); ?>" class="<?php echo $t === $type ? 'Selected' : ''; ?>"><?php echo h(item_type_name($t)); ?></a>
<?php } ?>
      </div>
      <p><?php echo h($help[$type]); ?></p>
<?php if ($type === ITEM_SHIRT || $type === ITEM_PANTS) { ?>
      <p><a href="<?php echo h(url('/img/Templates/' . ($type === ITEM_SHIRT ? 'Shirt' : 'Pants') . 'Template.png')); ?>" target="_blank"><img src="<?php echo h(url('/img/Templates/' . ($type === ITEM_SHIRT ? 'Shirt' : 'Pants') . 'Template.png')); ?>" width="234" height="224" alt="Template" class="TemplatePreview" /></a><br />
      <a href="<?php echo h(url('/img/Templates/' . ($type === ITEM_SHIRT ? 'Shirt' : 'Pants') . 'Template.png')); ?>" download="<?php echo $type === ITEM_SHIRT ? 'Shirt' : 'Pants'; ?>Template.png">Download the <?php echo h(strtolower(item_type_name($type))); ?> template</a></p>
<?php } ?>
<?php if ($error) { ?>
      <div class="validationWarningSmall"><?php echo h($error); ?></div>
<?php } ?>
      <form method="post" enctype="multipart/form-data" action="<?php echo h(url('/My/Create.aspx')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="Type" value="<?php echo (int)$type; ?>" />
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int)MAX_UPLOAD_BYTES; ?>" />
        <table cellpadding="4">
          <tr><td class="Label"><label for="Image">Image:</label></td><td><input id="Image" name="Image" type="file" accept="image/png,image/jpeg,image/gif" /></td></tr>
          <tr><td class="Label"><label for="Name">Name:</label></td><td><input id="Name" name="Name" type="text" class="TextBox" size="40" maxlength="50" value="<?php echo h($name); ?>" /></td></tr>
          <tr><td class="Label" valign="top"><label for="Description">Description:</label></td><td><textarea id="Description" name="Description" rows="4" cols="50" maxlength="500"><?php echo h($description); ?></textarea></td></tr>
          <tr><td></td><td><input type="submit" class="Button" value="Upload" /> <span class="Hint">It will be free in the catalog for everyone.</span></td></tr>
        </table>
      </form>
    </div>
  </div>
</div>
<?php
page_footer();
