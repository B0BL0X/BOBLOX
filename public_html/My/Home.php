<?php
/* "My BOBLOX": your dashboard with friend requests. */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
$me = require_login();
$requests = friend_requests_for($me['id']);
$friends = friends_of($me['id'], 6);

page_header('My ' . SITE_NAME);
?>
<div id="MyHome">
  <div id="MyHomeLeft" class="Panel">
    <h4>Hi, <?php echo h($me['username']); ?>!</h4>
    <div class="PanelContent" style="text-align:center">
      <a href="<?php echo h(url('/My/Character.aspx')); ?>"><?php echo avatar_image($me, 180); ?></a>
      <div><a class="Button" href="<?php echo h(url('/My/Character.aspx')); ?>">Change Character</a></div>
      <p>Your profile address:<br /><a href="<?php echo h(vanity_url($me['username'])); ?>"><?php echo h(vanity_url($me['username'])); ?></a></p>
      <ul class="BulletList" style="text-align:left">
        <li><a href="<?php echo h(profile_url($me['id'])); ?>">View my profile</a></li>
        <li><a href="<?php echo h(url('/My/Profile.aspx')); ?>">Edit my blurb &amp; settings</a></li>
        <li><a href="<?php echo h(url('/My/Create.aspx')); ?>">Make a T-Shirt, Shirt, Pants or Face</a></li>
        <li><a href="<?php echo h(url('/Catalog.aspx')); ?>">Get hats and clothes</a></li>
        <li><a href="<?php echo h(url('/Browse.aspx')); ?>">Find friends</a></li>
      </ul>
    </div>
  </div>
  <div id="MyHomeRight">
    <div class="Panel">
      <h4>Friend Requests (<?php echo count($requests); ?>)</h4>
      <div class="PanelContent">
<?php if (!$requests) { ?>
        <p class="Faded">You have no friend requests right now.</p>
<?php } else { ?>
        <table width="100%" cellpadding="4" cellspacing="0" class="RequestTable">
<?php foreach ($requests as $r) { ?>
          <tr id="FriendRequest_<?php echo (int)$r['id']; ?>">
            <td width="60"><a href="<?php echo h(profile_url($r['id'])); ?>"><?php echo avatar_image($r, 48); ?></a></td>
            <td><a href="<?php echo h(profile_url($r['id'])); ?>"><b><?php echo h($r['username']); ?></b></a> wants to be your friend.<br /><span class="Faded"><?php echo h(time_ago($r['requested'])); ?></span></td>
            <td align="right" nowrap="nowrap">
              <a class="Button" href="#" onclick="BOBLOX.Friends.answer(<?php echo (int)$r['id']; ?>, true); return false;">Accept</a>
              <a class="Button" href="#" onclick="BOBLOX.Friends.answer(<?php echo (int)$r['id']; ?>, false); return false;">Decline</a>
            </td>
          </tr>
<?php } ?>
        </table>
<?php } ?>
      </div>
    </div>
    <div class="Panel">
      <h4>My Friends (<?php echo friend_count($me['id']); ?>)</h4>
      <div class="PanelContent">
<?php if (!$friends) { ?>
        <p class="Faded">No friends yet. <a href="<?php echo h(url('/Browse.aspx')); ?>">Go find some!</a></p>
<?php } ?>
<?php foreach ($friends as $f) { ?>
        <div class="UserTile">
          <a href="<?php echo h(profile_url($f['id'])); ?>"><?php echo avatar_image($f, 100); ?></a><br />
          <a href="<?php echo h(profile_url($f['id'])); ?>"><?php echo h($f['username']); ?></a>
        </div>
<?php } ?>
        <div style="clear:both"></div>
<?php if (friend_count($me['id']) > count($friends)) { ?>
        <div class="PanelFooter"><a href="<?php echo h(url('/Browse.aspx?friendsOf=' . (int)$me['id'])); ?>">See all</a></div>
<?php } ?>
      </div>
    </div>
  </div>
  <div style="clear:both"></div>
</div>
<?php
page_footer();
