<?php
/* Admin: catalog (edit, delete/restore, official hats and uploads) */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_admin();

$meshes = hat_meshes();
$error = '';
$notice = get_int('saved') ? 'Saved!' : '';

if (is_post()) {
    csrf_check_form();
    $action = post_str('action');
    $name = trim(post_str('Name'));
    $description = trim(str_replace("\r\n", "\n", post_str('Description')));
    if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
        $error = 'Please give it a name (up to 50 characters).';
    } elseif (mb_strlen($description, 'UTF-8') > 500) {
        $error = 'The description can be at most 500 characters long.';
    } elseif ($action === 'save') {
        $item = db_row('SELECT * FROM items WHERE id = ?', array((int)post_str('ID')));
        if (!$item) {
            $error = 'That item does not exist.';
        } else {
            db_query('UPDATE items SET name = ?, description = ? WHERE id = ?', array($name, $description, (int)$item['id']));
            if ((int)$item['type'] === ITEM_HAT) {
                $mesh = post_str('Mesh');
                $color = post_str('Color');
                if (isset($meshes[$mesh]) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    db_query('UPDATE items SET mesh = ?, color = ? WHERE id = ?', array($mesh, strtolower($color), (int)$item['id']));
                    admin_store_item_thumbnail($item['id'], post_str('Thumbnail'));
                    // everyone wearing it gets a new picture
                    foreach (db_all('SELECT user_id FROM wearing WHERE item_id = ?', array((int)$item['id'])) as $w) avatar_invalidate((int)$w['user_id']);
                }
            }
            admin_log('Edited the item "' . $name . '" (#' . $item['id'] . ')');
            redirect(url('/Admin/Catalog.aspx?edit=' . (int)$item['id'] . '&saved=1'));
        }
    } elseif ($action === 'create_hat') {
        $mesh = post_str('Mesh');
        $color = post_str('Color');
        if (!isset($meshes[$mesh]) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $error = 'Pick a hat shape and a colour.';
        } else {
            db_query('INSERT INTO items (type, name, description, creator_id, mesh, color, created) VALUES (?, ?, ?, 0, ?, ?, ?)',
                array(ITEM_HAT, $name, $description, $mesh, strtolower($color), time()));
            $id = (int)db()->lastInsertId();
            admin_store_item_thumbnail($id, post_str('Thumbnail'));
            admin_log('Made the hat "' . $name . '" (#' . $id . ')');
            redirect(url('/Admin/Catalog.aspx?edit=' . $id . '&saved=1'));
        }
    } elseif ($action === 'create_upload') {
        $type = (int)post_str('Type');
        if (!in_array($type, uploadable_types(), true)) {
            $error = 'Pick what kind of item it is.';
        } else {
            $stored = store_item_upload(isset($_FILES['Image']) ? $_FILES['Image'] : null, $type);
            if (isset($stored['error'])) {
                $error = $stored['error'];
            } else {
                db_query('INSERT INTO items (type, name, description, creator_id, file, created) VALUES (?, ?, ?, 0, ?, ?)',
                    array($type, $name, $description, $stored['file'], time()));
                $id = (int)db()->lastInsertId();
                admin_log('Made the ' . item_type_name($type) . ' "' . $name . '" (#' . $id . ')');
                redirect(url('/Admin/Catalog.aspx?edit=' . $id . '&saved=1'));
            }
        }
    }
}

$editId = get_int('edit');
$new = get_str('new');
$editing = $editId ? db_row('SELECT i.*, u.username AS creator_name FROM items i LEFT JOIN users u ON u.id = i.creator_id WHERE i.id = ?', array($editId)) : null;
$isHatForm = ($editing && (int)$editing['type'] === ITEM_HAT) || $new === 'hat';

page_header('Admin: Catalog', array('three' => $isHatForm, 'scripts' => array('/js/Admin.js')));
?>
<div id="AdminContainer">
  <?php admin_tabs('Catalog'); ?>
  <div class="AdminSubLinks">
    <a href="<?php echo h(url('/Admin/Catalog.aspx')); ?>">All items</a> |
    <a href="<?php echo h(url('/Admin/Catalog.aspx?uploads=1')); ?>">People's uploads</a> |
    <a href="<?php echo h(url('/Admin/Catalog.aspx?deleted=1')); ?>">Deleted items</a> |
    <a href="<?php echo h(url('/Admin/Catalog.aspx?new=hat')); ?>"><b>+ New hat</b></a> |
    <a href="<?php echo h(url('/Admin/Catalog.aspx?new=upload')); ?>"><b>+ New official T-Shirt / Shirt / Pants / Face</b></a>
  </div>
<?php if ($error) { ?>
  <div class="validationWarningSmall"><?php echo h($error); ?></div>
<?php } ?>
<?php if ($notice) { ?>
  <div class="AdminNotice"><?php echo h($notice); ?></div>
<?php } ?>

<?php if ($editing || $new === 'hat') {
    $item = $editing ? $editing : array('id' => 0, 'type' => ITEM_HAT, 'name' => post_str('Name'), 'description' => post_str('Description'), 'mesh' => post_str('Mesh', 'tophat'), 'color' => post_str('Color', '#1b1b1b'), 'deleted' => 0, 'creator_id' => 0);
    ?>
  <div class="Panel">
    <h4><?php echo $editing ? 'Edit "' . h($item['name']) . '"' : 'New Hat'; ?></h4>
    <div class="PanelContent">
      <div class="AdminItemPreview">
<?php if ($isHatForm) { ?>
        <div id="HatPreview"></div>
        <div class="Hint">This picture is used in the catalog.</div>
<?php } else { ?>
        <img src="<?php echo h(item_thumb_url($item, 250)); ?>" width="250" height="250" alt="" />
<?php     if ($item['file']) { ?>
        <div><a href="<?php echo h(item_texture_url($item)); ?>" target="_blank">View the uploaded image</a></div>
<?php     } ?>
<?php } ?>
      </div>
      <form method="post" action="<?php echo h(url('/Admin/Catalog.aspx' . ($editing ? '?edit=' . (int)$item['id'] : '?new=hat'))); ?>" id="ItemForm" onsubmit="return BOBLOX.Admin.beforeItemSave(this);">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'save' : 'create_hat'; ?>" />
        <input type="hidden" name="ID" value="<?php echo (int)$item['id']; ?>" />
        <input type="hidden" name="Thumbnail" id="Thumbnail" value="" />
        <table cellpadding="4">
<?php if ($editing) { ?>
          <tr><td class="Label">Item:</td><td>#<?php echo (int)$item['id']; ?> &middot; <?php echo h(item_type_name((int)$item['type'])); ?> by <?php echo h(item_creator_name($item)); ?> &middot; taken <?php echo (int)$item['takes']; ?> times
            <?php if ((int)$item['deleted'] === 1) echo ' <span class="BannedTag">Deleted</span>'; else echo ' &middot; <a href="' . h(item_url($item)) . '">view in catalog</a>'; ?></td></tr>
<?php } ?>
          <tr><td class="Label"><label for="Name">Name:</label></td><td><input id="Name" name="Name" type="text" class="TextBox" size="40" maxlength="50" value="<?php echo h($item['name']); ?>" /></td></tr>
          <tr><td class="Label" valign="top"><label for="Description">Description:</label></td><td><textarea id="Description" name="Description" rows="4" cols="50" maxlength="500"><?php echo h($item['description']); ?></textarea></td></tr>
<?php if ($isHatForm) { ?>
          <tr><td class="Label"><label for="Mesh">Shape:</label></td><td>
            <select id="Mesh" name="Mesh" onchange="BOBLOX.Admin.updateHatPreview()">
<?php foreach ($meshes as $key => $label) { ?>
              <option value="<?php echo h($key); ?>"<?php echo $key === $item['mesh'] ? ' selected="selected"' : ''; ?>><?php echo h($label); ?></option>
<?php } ?>
            </select></td></tr>
          <tr><td class="Label"><label for="Color">Colour:</label></td><td><input id="Color" name="Color" type="color" value="<?php echo h($item['color'] ? $item['color'] : '#1b1b1b'); ?>" oninput="BOBLOX.Admin.updateHatPreview()" /></td></tr>
<?php } ?>
          <tr><td></td><td>
            <input type="submit" class="Button" value="<?php echo $editing ? 'Save' : 'Create Hat'; ?>" />
<?php if ($editing) { ?>
<?php     if ((int)$item['deleted'] === 1) { ?>
            <a href="#" class="Button" onclick="BOBLOX.Admin.restoreItem(this, <?php echo (int)$item['id']; ?>); return false;">Restore</a>
<?php     } else { ?>
            <a href="#" class="Button DangerButton" onclick="BOBLOX.Admin.deleteItem(this, <?php echo (int)$item['id']; ?>); return false;">Delete</a>
<?php     } ?>
<?php } ?>
          </td></tr>
        </table>
      </form>
      <div style="clear:both"></div>
    </div>
  </div>
<?php if ($isHatForm) { ?>
  <script type="text/javascript">window.addEventListener('load', function () { BOBLOX.Admin.initHatPreview(); });</script>
<?php } ?>

<?php } elseif ($new === 'upload') { ?>
  <div class="Panel">
    <h4>New Official Item</h4>
    <div class="PanelContent">
      <p>Official items show <b><?php echo h(SITE_NAME); ?></b> as the creator. Shirts and Pants use the <a href="<?php echo h(url('/img/Templates/ShirtTemplate.png')); ?>" target="_blank">585 x 559 template</a>.</p>
      <form method="post" enctype="multipart/form-data" action="<?php echo h(url('/Admin/Catalog.aspx?new=upload')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_upload" />
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int)MAX_UPLOAD_BYTES; ?>" />
        <table cellpadding="4">
          <tr><td class="Label"><label for="Type">Type:</label></td><td><select id="Type" name="Type">
<?php foreach (uploadable_types() as $t) { ?>
            <option value="<?php echo $t; ?>"<?php echo (int)post_str('Type') === $t ? ' selected="selected"' : ''; ?>><?php echo h(item_type_name($t)); ?></option>
<?php } ?>
          </select></td></tr>
          <tr><td class="Label"><label for="Image">Image:</label></td><td><input id="Image" name="Image" type="file" accept="image/png,image/jpeg,image/gif" /></td></tr>
          <tr><td class="Label"><label for="Name">Name:</label></td><td><input id="Name" name="Name" type="text" class="TextBox" size="40" maxlength="50" value="<?php echo h(post_str('Name')); ?>" /></td></tr>
          <tr><td class="Label" valign="top"><label for="Description">Description:</label></td><td><textarea id="Description" name="Description" rows="4" cols="50" maxlength="500"><?php echo h(post_str('Description')); ?></textarea></td></tr>
          <tr><td></td><td><input type="submit" class="Button" value="Upload" /></td></tr>
        </table>
      </form>
    </div>
  </div>

<?php } else {
    // ---- list ----
    $type = get_int('type');
    $q = trim(get_str('q'));
    $deleted = get_int('deleted') ? 1 : 0;
    $uploads = get_int('uploads') ? 1 : 0;
    $page = max(1, get_int('p', 1));
    $perPage = 25;
    $where = array('i.deleted = ?');
    $params = array($deleted);
    if ($type && isset(item_types()[$type])) { $where[] = 'i.type = ?'; $params[] = $type; }
    if ($uploads) $where[] = 'i.creator_id <> 0';
    if ($q !== '') { $where[] = "i.name LIKE ? ESCAPE '\\'"; $params[] = '%' . addcslashes($q, '%_\\') . '%'; }
    $whereSql = implode(' AND ', $where);
    $total = (int)db_value("SELECT COUNT(*) FROM items i WHERE $whereSql", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $items = db_all("SELECT i.*, u.username AS creator_name FROM items i LEFT JOIN users u ON u.id = i.creator_id WHERE $whereSql ORDER BY i.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
    $link = function ($p) use ($type, $q, $deleted, $uploads) {
        return url('/Admin/Catalog.aspx?' . http_build_query(array_filter(array('type' => $type, 'q' => $q, 'deleted' => $deleted, 'uploads' => $uploads, 'p' => $p))));
    };
    ?>
  <div class="Panel">
    <h4><?php echo $deleted ? 'Deleted Items' : ($uploads ? "People's Uploads" : 'All Items'); ?> (<?php echo $total; ?>)</h4>
    <div class="PanelContent">
      <form method="get" action="<?php echo h(url('/Admin/Catalog.aspx')); ?>" class="AdminSearch">
        <?php if ($deleted) echo '<input type="hidden" name="deleted" value="1" />'; ?>
        <?php if ($uploads) echo '<input type="hidden" name="uploads" value="1" />'; ?>
        <input type="text" name="q" class="TextBox" value="<?php echo h($q); ?>" maxlength="50" />
        <select name="type">
          <option value="">All types</option>
<?php foreach (item_types() as $t => $info) { ?>
          <option value="<?php echo $t; ?>"<?php echo $type === $t ? ' selected="selected"' : ''; ?>><?php echo h($info[1]); ?></option>
<?php } ?>
        </select>
        <input type="submit" class="Button" value="Search" />
      </form>
      <table width="100%" cellpadding="4" cellspacing="0" class="AdminTable">
        <tr><th width="52"></th><th align="left">Name</th><th align="left">Type</th><th align="left">Creator</th><th>Taken</th><th align="left">Made</th><th align="left">Actions</th></tr>
<?php foreach ($items as $item) { ?>
        <tr id="Item_<?php echo (int)$item['id']; ?>">
          <td><img src="<?php echo h(item_thumb_url($item, 48)); ?>" width="48" height="48" alt="" /></td>
          <td><a href="<?php echo h(url('/Admin/Catalog.aspx?edit=' . (int)$item['id'])); ?>"><b><?php echo h($item['name']); ?></b></a></td>
          <td><?php echo h(item_type_name((int)$item['type'])); ?></td>
          <td><?php echo (int)$item['creator_id'] === 0 ? '<i>' . h(SITE_NAME) . '</i>' : '<a href="' . h(profile_url($item['creator_id'])) . '">' . h(item_creator_name($item)) . '</a>'; ?></td>
          <td align="center"><?php echo (int)$item['takes']; ?></td>
          <td><?php echo h(format_date($item['created'])); ?></td>
          <td class="AdminActions">
            <a class="Button" href="<?php echo h(url('/Admin/Catalog.aspx?edit=' . (int)$item['id'])); ?>">Edit</a>
<?php if ($deleted) { ?>
            <a href="#" class="Button" onclick="BOBLOX.Admin.restoreItem(this, <?php echo (int)$item['id']; ?>); return false;">Restore</a>
<?php } else { ?>
            <a href="#" class="Button DangerButton" onclick="BOBLOX.Admin.deleteItem(this, <?php echo (int)$item['id']; ?>); return false;">Delete</a>
<?php } ?>
          </td>
        </tr>
<?php } ?>
      </table>
<?php if (!$items) { ?>
      <p class="Faded">No items found.</p>
<?php } ?>
      <?php echo render_pager($page, $pages, $link); ?>
    </div>
  </div>
<?php } ?>
</div>
<?php
page_footer();
