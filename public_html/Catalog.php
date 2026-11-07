<?php
/* Catalog: /Catalog.aspx?c=Hats&q=&sort=new&p=1  (or ?creator=ID) */
require_once __DIR__ . '/App_Code/Bootstrap.php';

$types = item_types();
$type = item_type_from_slug(get_str('c', 'Hats'));
if ($type === null) $type = ITEM_HAT;
$creatorId = get_int('creator');
$creator = $creatorId ? db_row('SELECT id, username FROM users WHERE id = ?', array($creatorId)) : null;
$q = trim(get_str('q'));
$sort = get_str('sort') === 'popular' ? 'popular' : 'new';
$page = max(1, get_int('p', 1));
$perPage = 20;

$where = array('i.deleted = 0');
$params = array();
if ($creator) {
    $where[] = 'i.creator_id = ?';
    $params[] = (int)$creator['id'];
} else {
    $where[] = 'i.type = ?';
    $params[] = $type;
}
if ($q !== '') {
    $where[] = "i.name LIKE ? ESCAPE '\\'";
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
$whereSql = implode(' AND ', $where);
$total = (int)db_value("SELECT COUNT(*) FROM items i WHERE $whereSql", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$order = $sort === 'popular' ? 'i.takes DESC, i.id DESC' : 'i.created DESC, i.id DESC';
$items = db_all("SELECT i.*, u.username AS creator_name FROM items i LEFT JOIN users u ON u.id = i.creator_id WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

$me = current_user();
$owned = array();
if ($me && $items) {
    $ids = array_map(function ($i) { return (int)$i['id']; }, $items);
    foreach (db_all('SELECT item_id FROM inventory WHERE user_id = ? AND item_id IN (' . implode(',', $ids) . ')', array((int)$me['id'])) as $r) {
        $owned[(int)$r['item_id']] = true;
    }
}

$link = function ($changes) use ($type, $types, $q, $sort, $creator) {
    $args = array_merge(array('c' => $types[$type][2], 'q' => $q, 'sort' => $sort === 'new' ? '' : $sort, 'creator' => $creator ? $creator['id'] : ''), $changes);
    return url('/Catalog.aspx?' . http_build_query(array_filter($args, 'strlen')));
};

page_header($creator ? $creator['username'] . "'s Creations" : item_type_name($type, true) . ' Catalog');
?>
<div id="CatalogContainer">
  <div id="CatalogCategories" class="Panel">
    <h4>Category</h4>
    <ul>
<?php foreach ($types as $id => $t) { ?>
      <li class="<?php echo !$creator && $id === $type ? 'Selected' : ''; ?>"><a href="<?php echo h(url('/Catalog.aspx?c=' . $t[2])); ?>"><?php echo h($t[1]); ?></a></li>
<?php } ?>
    </ul>
<?php if ($me) { ?>
    <div class="CategoryFooter">
      <a href="<?php echo h(url('/My/Create.aspx')); ?>">Make your own!</a>
    </div>
<?php } ?>
  </div>
  <div id="CatalogItems" class="Panel">
    <h4><?php echo $creator ? h($creator['username']) . "'s Creations" : h(item_type_name($type, true)); ?></h4>
    <div class="PanelContent">
      <form method="get" action="<?php echo h(url('/Catalog.aspx')); ?>" class="SearchBar">
        <input type="hidden" name="c" value="<?php echo h($types[$type][2]); ?>" />
        <input type="text" name="q" class="TextBox" value="<?php echo h($q); ?>" maxlength="50" />
        <input type="submit" class="Button" value="Search" />
        <span class="SortBy">Sort by:
          <?php echo $sort === 'new' ? '<b>Newest</b>' : '<a href="' . h($link(array('sort' => ''))) . '">Newest</a>'; ?> |
          <?php echo $sort === 'popular' ? '<b>Most Taken</b>' : '<a href="' . h($link(array('sort' => 'popular'))) . '">Most Taken</a>'; ?>
        </span>
      </form>
<?php if (!$items) { ?>
      <p class="Faded">Nothing here yet.<?php if ($me && in_array($type, uploadable_types(), true)) { ?> <a href="<?php echo h(url('/My/Create.aspx?type=' . $type)); ?>">Make the first one!</a><?php } ?></p>
<?php } ?>
      <div class="Assets">
<?php foreach ($items as $item) {
    $extra = '<div class="AssetTakes"><span class="Label">Taken:</span> ' . (int)$item['takes'] . '</div>';
    if (isset($owned[(int)$item['id']])) $extra .= '<div class="AssetOwned">You own this</div>';
    echo item_tile($item, $extra, 110);
} ?>
      </div>
      <div style="clear:both"></div>
      <?php echo render_pager($page, $pages, function ($p) use ($link) { return $link(array('p' => $p)); }); ?>
    </div>
  </div>
  <div style="clear:both"></div>
</div>
<?php
page_footer();
