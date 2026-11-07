<?php
/* Profile page: /User.aspx?ID=1, /users/1/profile or /username */
require_once __DIR__ . '/App_Code/Bootstrap.php';

if (get_str('name') !== '') {
    $user = db_row('SELECT * FROM users WHERE username = ?', array(get_str('name')));
} else {
    $id = get_int('ID', get_int('id', get_int('userId')));
    if (!$id && current_user()) $id = current_user_id();
    $user = db_row('SELECT * FROM users WHERE id = ?', array($id));
}

if (!$user) {
    http_response_code(404);
    page_header('User Not Found');
    echo '<div class="FormPage"><div class="Panel"><h4>User Not Found</h4><div class="PanelContent"><p>That user does not exist. <a href="' . h(url('/Browse.aspx')) . '">Look for people</a></p></div></div></div>';
    page_footer();
    exit;
}

if ((int)$user['banned'] === 1 && !is_admin()) {
    page_header($user['username']);
    echo '<div class="FormPage"><div class="Panel"><h4>' . h($user['username']) . '</h4><div class="PanelContent"><p>This account has been banned.</p></div></div></div>';
    page_footer();
    exit;
}

// Count one view per visitor session (and not your own).
$viewed = isset($_SESSION['viewed']) ? $_SESSION['viewed'] : array();
if ((int)$user['id'] !== current_user_id() && !isset($viewed[$user['id']])) {
    db_query('UPDATE users SET profile_views = profile_views + 1 WHERE id = ?', array((int)$user['id']));
    $user['profile_views']++;
    $viewed[$user['id']] = 1;
    $_SESSION['viewed'] = array_slice($viewed, -200, null, true);
}

$me = current_user();
$isMe = $me && (int)$me['id'] === (int)$user['id'];
$friendStatus = $me ? friend_status($me['id'], $user['id']) : 'none';
$friendCount = friend_count($user['id']);
$friends = friends_of($user['id'], 9);
$wearing = avatar_wearing($user['id']);
$creations = db_all('SELECT i.*, ? AS creator_name FROM items i WHERE creator_id = ? AND deleted = 0 ORDER BY created DESC LIMIT 6', array($user['username'], (int)$user['id']));
$createdCount = (int)db_value('SELECT COUNT(*) FROM items WHERE creator_id = ? AND deleted = 0', array((int)$user['id']));
$badges = user_badges($user);
$name = $user['username'];
$friendLabels = array('none' => 'Send Friend Request', 'sent' => 'Friend Request Sent (cancel)', 'received' => 'Accept Friend Request', 'friends' => 'Remove Friend');

page_header($name . "'s Profile", array('three' => true));
?>
<script type="text/javascript">
//<![CDATA[
window.ProfileAppearance = <?php echo json_encode(avatar_appearance($user), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
//]]>
</script>
<div id="UserContainer">
  <div id="LeftBank">
    <div id="ProfilePane">
      <table width="442" cellspacing="0" cellpadding="6">
        <tr>
          <td>
            <span class="Title"><?php echo h($name); ?></span><br />
            <?php echo online_label($user); ?>
          </td>
        </tr>
        <tr>
          <td>
            <span><?php echo h($name); ?>'s <?php echo h(SITE_NAME); ?>:</span><br />
            <a href="<?php echo h(vanity_url($name)); ?>"><?php echo h(vanity_url($name)); ?></a><br />
            <br />
            <div class="ProfileAvatar">
              <img id="AvatarImage" src="<?php echo h(avatar_thumb_url($user, 250)); ?>" width="250" height="250" alt="<?php echo h($name); ?>" border="0" />
              <div id="Avatar3DHolder" style="display:none;width:250px;height:250px"></div>
              <div class="AvatarLinks">
                <a href="#" onclick="BOBLOX.Profile.show3D(this, <?php echo (int)$user['id']; ?>); return false;">[ View in 3D ]</a>
<?php if ($isMe) { ?>
                <a href="<?php echo h(url('/My/Character.aspx')); ?>">[ Change Character ]</a>
<?php } ?>
              </div>
            </div>
            <div class="ProfileBlurb">
<?php if (trim($user['blurb']) !== '') { ?>
              <p><span style="white-space:pre-wrap;word-wrap:break-word;"><?php echo h($user['blurb']); ?></span></p>
<?php } else { ?>
              <p class="Faded"><?php echo $isMe ? 'You have not written anything about yourself yet.' : h($name) . ' has not written anything yet.'; ?></p>
<?php } ?>
<?php if ($user['discord'] !== '') { ?>
              <p><a href="<?php echo h($user['discord']); ?>" rel="nofollow noopener" target="_blank"><img src="<?php echo h(url('/img/newpost.gif')); ?>" border="0" alt="" /></a> <a href="<?php echo h($user['discord']); ?>" rel="nofollow noopener" target="_blank">My Discord</a></p>
<?php } ?>
            </div>
            <div style="clear:both"></div>
            <div class="ProfileActions">
<?php if ($isMe) { ?>
              <a class="Button" href="<?php echo h(url('/My/Profile.aspx')); ?>">Edit Profile</a>
              <a class="Button" href="<?php echo h(url('/My/Character.aspx')); ?>">Change Character</a>
<?php } elseif ($me) { ?>
              <a href="#" class="Button" data-status="<?php echo h($friendStatus); ?>" onclick="BOBLOX.Friends.toggle(this, <?php echo (int)$user['id']; ?>); return false;"><?php echo h($friendLabels[$friendStatus]); ?></a>
<?php } else { ?>
              <a class="Button" href="<?php echo h(url('/Login/Default.aspx?ReturnUrl=' . rawurlencode(profile_url($user['id'])))); ?>">Login to add <?php echo h($name); ?> as a friend</a>
<?php } ?>
            </div>
          </td>
        </tr>
      </table>
    </div>

    <div id="UserBadgesPane">
      <div id="UserBadges">
        <h4>Badges</h4>
<?php if (!$badges) { ?>
        <p class="Faded" style="padding:10px;text-align:center"><?php echo h($name); ?> has not earned any badges yet.</p>
<?php } ?>
<?php foreach ($badges as $b) { ?>
        <div class="Badge">
          <div class="BadgeImage">
            <img src="<?php echo h($b[1]); ?>" width="75" height="75" title="<?php echo h($b[2]); ?>" alt="<?php echo h($b[0]); ?>" /><br />
            <div class="BadgeLabel"><?php echo h($b[0]); ?></div>
          </div>
        </div>
<?php } ?>
        <div style="clear:both"></div>
      </div>
    </div>

    <div id="UserStatisticsPane">
      <div id="UserStatistics">
        <h4>Statistics</h4>
        <div style="margin: 10px 10px 10px 10px;" id="Results">
          <div class="Statistic"><div class="Label"><acronym title="The day this user joined.">Joined</acronym>:</div><div class="Value"><span><?php echo h(format_date($user['created'])); ?></span></div></div>
          <div class="Statistic"><div class="Label"><acronym title="The number of this user's friends.">Friends</acronym>:</div><div class="Value"><span><?php echo $friendCount; ?></span></div></div>
          <div class="Statistic"><div class="Label"><acronym title="The number of times this user's profile has been viewed.">Profile Views</acronym>:</div><div class="Value"><span><?php echo (int)$user['profile_views']; ?></span></div></div>
          <div class="Statistic"><div class="Label"><acronym title="The number of catalog items this user has made.">Items Created</acronym>:</div><div class="Value"><span><?php echo $createdCount; ?></span></div></div>
          <div class="Statistic"><div class="Label"><acronym title="The last time this user was on the site.">Last Online</acronym>:</div><div class="Value"><span><?php echo is_online($user) ? 'Now' : h(time_ago($user['last_seen'])); ?></span></div></div>
          <div style="clear:both"></div>
        </div>
      </div>
    </div>
  </div>

  <div id="RightBank">
    <div id="FriendsPane">
      <h4><?php echo h($name); ?>'s friends <a href="<?php echo h(url('/Browse.aspx?friendsOf=' . (int)$user['id'])); ?>">(<?php echo $friendCount; ?>)</a></h4>
      <div id="Friends">
<?php if (!$friends) { ?>
        <p style="padding: 10px 10px 10px 10px;">This person doesn't have any <?php echo h(SITE_NAME); ?> friends</p>
<?php } else { ?>
        <table align="center" border="0" cellspacing="0" cellpadding="4">
<?php foreach (array_chunk($friends, 3) as $row) { ?>
          <tr>
<?php foreach ($row as $f) { ?>
            <td align="center" valign="top" class="Friend">
              <a href="<?php echo h(profile_url($f['id'])); ?>"><?php echo avatar_image($f, 100); ?></a><br />
              <?php echo is_online($f) ? '<img src="' . h(url('/img/Online.gif')) . '" alt="online" /> ' : ''; ?><a href="<?php echo h(profile_url($f['id'])); ?>"><?php echo h($f['username']); ?></a>
            </td>
<?php } ?>
          </tr>
<?php } ?>
        </table>
<?php } ?>
      </div>
    </div>

    <div id="WearingPane" class="SidePane">
      <h4>Currently Wearing</h4>
      <div class="SidePaneContent">
<?php if (!$wearing) { ?>
        <p class="Faded">Nothing but a smile.</p>
<?php } ?>
<?php foreach ($wearing as $item) { ?>
        <div class="MiniAsset">
          <a href="<?php echo h(item_url($item)); ?>"><img src="<?php echo h(item_thumb_url($item, 75)); ?>" width="75" height="75" alt="<?php echo h($item['name']); ?>" title="<?php echo h($item['name']); ?>" border="0" /></a><br />
          <a href="<?php echo h(item_url($item)); ?>"><?php echo h($item['name']); ?></a>
        </div>
<?php } ?>
        <div style="clear:both"></div>
      </div>
    </div>

    <div id="CreationsPane" class="SidePane">
      <h4><?php echo h($name); ?>'s Creations</h4>
      <div class="SidePaneContent">
<?php if (!$creations) { ?>
        <p class="Faded"><?php echo h($name); ?> has not made anything yet.</p>
<?php } ?>
<?php foreach ($creations as $item) { ?>
        <div class="MiniAsset">
          <a href="<?php echo h(item_url($item)); ?>"><img src="<?php echo h(item_thumb_url($item, 75)); ?>" width="75" height="75" alt="<?php echo h($item['name']); ?>" title="<?php echo h($item['name']); ?>" border="0" /></a><br />
          <a href="<?php echo h(item_url($item)); ?>"><?php echo h($item['name']); ?></a>
        </div>
<?php } ?>
        <div style="clear:both"></div>
<?php if ($createdCount > count($creations)) { ?>
        <div class="PanelFooter"><a href="<?php echo h(url('/Catalog.aspx?creator=' . (int)$user['id'])); ?>">See all <?php echo $createdCount; ?></a></div>
<?php } ?>
      </div>
    </div>
  </div>
  <div style="clear:both"></div>
</div>
<?php
page_footer();
