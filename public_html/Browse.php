<?php
/* People search: /Browse.aspx?q=name  or  /Browse.aspx?friendsOf=1 */
require_once __DIR__ . '/App_Code/Bootstrap.php';

$q = trim(get_str('q'));
$friendsOf = get_int('friendsOf');
$page = max(1, get_int('p', 1));
$perPage = 15;
$owner = $friendsOf ? db_row('SELECT * FROM users WHERE id = ?', array($friendsOf)) : null;

if ($owner) {
    $total = friend_count($owner['id']);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $users = friends_of($owner['id'], $perPage, ($page - 1) * $perPage);
    $title = $owner['username'] . "'s Friends";
} else {
    $where = 'WHERE banned = 0';
    $params = array();
    if ($q !== '') {
        $where = "WHERE banned = 0 AND username LIKE ? ESCAPE '\\'";
        $params[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    $total = (int)db_value("SELECT COUNT(*) FROM users $where", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $users = db_all("SELECT * FROM users $where ORDER BY last_seen DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
    $title = 'People';
}

page_header($title);
?>
<div id="BrowseContainer">
  <div class="Panel">
    <h4><?php echo h($title); ?></h4>
    <div class="PanelContent">
      <form method="get" action="<?php echo h(url('/Browse.aspx')); ?>" class="SearchBar">
        <input type="text" name="q" class="TextBox" value="<?php echo h($q); ?>" maxlength="20" />
        <input type="submit" class="Button" value="Search Users" />
<?php if ($q !== '' || $owner) { ?>
        <a href="<?php echo h(url('/Browse.aspx')); ?>">Show everyone</a>
<?php } ?>
      </form>
<?php if (!$users) { ?>
      <p class="Faded">No one was found.</p>
<?php } else { ?>
      <table class="BrowseTable" width="100%" cellspacing="0" cellpadding="4">
        <tr><th width="60">Avatar</th><th align="left">Name</th><th align="left">Blurb</th><th width="120" align="left">Last Seen</th></tr>
<?php foreach ($users as $i => $u) { ?>
        <tr class="<?php echo $i % 2 ? 'AlternatingItem' : 'Item'; ?>">
          <td align="center"><a href="<?php echo h(profile_url($u['id'])); ?>"><?php echo avatar_image($u, 48); ?></a></td>
          <td><a href="<?php echo h(profile_url($u['id'])); ?>" class="UserName"><?php echo h($u['username']); ?></a><?php if ((int)$u['is_admin'] === 1) echo ' <span class="AdminTag">Admin</span>'; ?></td>
          <td class="Blurb"><?php echo h(mb_strimwidth(str_replace("\n", ' ', $u['blurb']), 0, 90, '...', 'UTF-8')); ?></td>
          <td><?php echo is_online($u) ? '<span class="UserOnlineMessage">Online</span>' : h(time_ago($u['last_seen'])); ?></td>
        </tr>
<?php } ?>
      </table>
<?php } ?>
      <?php echo render_pager($page, $pages, function ($p) use ($q, $owner) {
          return url('/Browse.aspx?' . http_build_query(array_filter(array('q' => $q, 'friendsOf' => $owner ? $owner['id'] : null, 'p' => $p))));
      }); ?>
    </div>
  </div>
</div>
<?php
page_footer();
