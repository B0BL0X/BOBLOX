<?php
/* Item page: /Item.aspx?ID=5 */
require_once __DIR__ . '/App_Code/Bootstrap.php';

$item = get_item(get_int('ID', get_int('id')));
if (!$item) {
    http_response_code(404);
    page_header('Item Not Found');
    echo '<div class="FormPage"><div class="Panel"><h4>Item Not Found</h4><div class="PanelContent"><p>That item does not exist or was deleted. <a href="' . h(url('/Catalog.aspx')) . '">Back to the catalog</a></p></div></div></div>';
    page_footer();
    exit;
}

$me = current_user();
$owns = $me && owns_item($me['id'], $item['id']);
$wearingIt = $me && db_value('SELECT 1 FROM wearing WHERE user_id = ? AND item_id = ?', array((int)$me['id'], (int)$item['id']));
$typeName = item_type_name((int)$item['type']);
$creatorName = item_creator_name($item);

page_header($item['name']);
?>
<div id="ItemContainer" class="Panel">
  <h4><?php echo h($item['name']); ?></h4>
  <div class="PanelContent">
    <div id="ItemThumbnail">
      <img src="<?php echo h(item_thumb_url($item, 250)); ?>" width="250" height="250" alt="<?php echo h($item['name']); ?>" />
    </div>
    <div id="ItemDetails">
      <table cellpadding="3">
        <tr><td class="Label">Type:</td><td><?php echo h($typeName); ?></td></tr>
        <tr><td class="Label">Creator:</td><td><?php echo (int)$item['creator_id'] === 0 ? h($creatorName) : '<a href="' . h(profile_url($item['creator_id'])) . '">' . h($creatorName) . '</a>'; ?></td></tr>
        <tr><td class="Label">Created:</td><td><?php echo h(format_date($item['created'])); ?></td></tr>
        <tr><td class="Label">Taken:</td><td><?php echo (int)$item['takes']; ?> time<?php echo (int)$item['takes'] === 1 ? '' : 's'; ?></td></tr>
        <tr><td class="Label">Price:</td><td><span class="Free">FREE</span></td></tr>
      </table>
<?php if (trim($item['description']) !== '') { ?>
      <div id="ItemDescription"><?php echo multiline($item['description']); ?></div>
<?php } ?>
      <div id="ItemActions">
<?php if (!$me) { ?>
        <a class="Button" href="<?php echo h(url('/Login/Default.aspx?ReturnUrl=' . rawurlencode(item_url($item)))); ?>">Login to get this item</a>
<?php } elseif (!$owns) { ?>
        <a class="Button" href="#" onclick="BOBLOX.Catalog.take(this, <?php echo (int)$item['id']; ?>, false); return false;">Get it free!</a>
        <a class="Button" href="#" onclick="BOBLOX.Catalog.take(this, <?php echo (int)$item['id']; ?>, true); return false;">Get it &amp; wear it</a>
<?php } else { ?>
        <div class="AssetOwned">You own this <?php echo h(strtolower($typeName)); ?><?php echo $wearingIt ? ' and you are wearing it' : ''; ?>.</div>
        <a class="Button" href="<?php echo h(url('/My/Character.aspx?type=' . (int)$item['type'])); ?>"><?php echo $wearingIt ? 'Change Character' : 'Wear it on the Character page'; ?></a>
<?php } ?>
<?php if (can_manage_item($item)) { ?>
        <div class="AdminPanel">
          <a class="Button" href="#" onclick="BOBLOX.Catalog.remove(this, <?php echo (int)$item['id']; ?>); return false;">Delete Item</a>
<?php if ($item['file'] && (int)$item['type'] !== ITEM_FACE) { ?>
          <a class="Button" href="<?php echo h(item_texture_url($item)); ?>" target="_blank">View Template</a>
<?php } ?>
        </div>
<?php } ?>
      </div>
    </div>
    <div style="clear:both"></div>
  </div>
</div>
<?php
page_footer();
