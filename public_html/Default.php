<?php
/* Home page: the "BOBLOX Info" thread from the old static page, plus newest members. */
require_once __DIR__ . '/App_Code/Bootstrap.php';

$owner = db_row('SELECT * FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1');
$newest = db_all('SELECT * FROM users ORDER BY id DESC LIMIT 8');
$memberCount = (int)db_value('SELECT COUNT(*) FROM users');
$onlineCount = (int)db_value('SELECT COUNT(*) FROM users WHERE last_seen > ?', array(time() - 300));
$me = current_user();

page_header('');
?>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr valign="top">
    <td>&nbsp;&nbsp;&nbsp;</td>
    <td width="95%" class="CenterColumn">
      <br />
      <table cellpadding="0" cellspacing="0" width="100%">
        <tr>
          <td class="popupMenuSink" width="1px" valign="top" align="left">
            <nobr><a class="linkMenuSink" href="<?php echo h($owner ? profile_url($owner['id']) : url('/Default.aspx')); ?>"><?php echo h($owner ? $owner['username'] : SITE_NAME); ?></a></nobr>
          </td>
          <td class="popupMenuSink" width="1px" valign="top" align="left">
            <nobr><span class="normalTextSmallBold">&nbsp;&gt;</span> <a class="linkMenuSink" href="#"><?php echo h(SITE_NAME); ?> Info</a></nobr>
          </td>
          <td width="*">&nbsp;</td>
        </tr>
      </table>
      <br />
      <table class="tableBorder" cellspacing="1" cellpadding="0" border="0" width="100%">
        <tr>
          <th class="tableHeaderText" align="left" height="25" width="100">&nbsp;Author</th>
          <th class="tableHeaderText" align="left" width="85%">&nbsp;Thread: <?php echo h(SITE_NAME); ?> Info</th>
        </tr>
        <tr>
          <td class="forumRow" valign="top" width="150" nowrap="nowrap">
            <table border="0">
<?php if ($owner) { ?>
              <tr><td><img src="<?php echo h(url(is_online($owner) ? '/img/Online.gif' : '/img/Offline.gif')); ?>" border="0" alt="" />&nbsp;<a class="normalTextSmallBold" href="<?php echo h(profile_url($owner['id'])); ?>"><?php echo h($owner['username']); ?></a></td></tr>
              <tr><td><a href="<?php echo h(profile_url($owner['id'])); ?>"><?php echo avatar_image($owner, 100); ?></a></td></tr>
              <tr><td><span class="normalTextSmaller"><b>Joined:</b> <?php echo h(date('d M Y', (int)$owner['created'])); ?></span></td></tr>
<?php } else { ?>
              <tr><td><span class="normalTextSmallBold"><?php echo h(SITE_NAME); ?></span></td></tr>
              <tr><td><img src="<?php echo h(url('/img/525c48a16d539c17460dddd939ab0276.png')); ?>" width="64" border="0" alt="" /></td></tr>
<?php } ?>
              <tr><td><span class="normalTextSmaller"><b>Total Posts: </b>1</span></td></tr>
              <tr><td>&nbsp;</td></tr>
            </table>
          </td>
          <td class="forumRow" valign="top">
            <table cellspacing="0" cellpadding="3" border="0" width="100%">
              <tr>
                <td class="forumRowHighlight"><span class="normalTextSmallBold"><?php echo h(HOME_POST_TITLE); ?></span><br /><span class="normalTextSmaller"> Posted: </span><span class="normalTextSmaller"><?php echo h(date('d F Y g:i A', $owner ? (int)$owner['created'] : time())); ?></span></td>
              </tr>
              <tr>
                <td colspan="2"><span class="normalTextSmall"><?php echo multiline(HOME_POST_BODY); ?></span></td>
              </tr>
              <tr><td height="2"></td></tr>
              <tr>
                <td colspan="2">
<?php if (OWNER_DISCORD_URL !== '') { ?>
                  <a href="<?php echo h(OWNER_DISCORD_URL); ?>"><img border="0" src="<?php echo h(url('/img/newpost.gif')); ?>" alt="Message the owner" /></a>
<?php } ?>
<?php if (CONTACT_EMAIL !== '') { ?>
                  <a href="mailto:<?php echo h(CONTACT_EMAIL); ?>" style="font-size: 12px;">Contact Owner</a>
<?php } ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <div id="HomeColumns">
        <div class="Panel" id="NewestMembers">
          <h4>Newest <?php echo h(SITE_NAME); ?>ians</h4>
          <div class="PanelContent">
<?php if (!$newest) { ?>
            <p>No one has signed up yet. <a href="<?php echo h(url('/Login/NewAccount.aspx')); ?>">Be the first!</a> (The first account becomes the administrator.)</p>
<?php } ?>
<?php foreach ($newest as $u) { ?>
            <div class="UserTile">
              <a href="<?php echo h(profile_url($u['id'])); ?>"><?php echo avatar_image($u, 100); ?></a><br />
              <a href="<?php echo h(profile_url($u['id'])); ?>"><?php echo h($u['username']); ?></a>
            </div>
<?php } ?>
            <div style="clear:both"></div>
          </div>
        </div>
        <div class="Panel" id="SiteStats">
          <h4><?php echo $me ? 'Welcome back, ' . h($me['username']) . '!' : 'Join ' . h(SITE_NAME) . '!'; ?></h4>
          <div class="PanelContent">
<?php if ($me) { ?>
            <a href="<?php echo h(url('/My/Character.aspx')); ?>"><?php echo avatar_image($me, 150); ?></a>
            <ul class="BulletList">
              <li><a href="<?php echo h(url('/My/Character.aspx')); ?>">Change your character</a></li>
              <li><a href="<?php echo h(profile_url($me['id'])); ?>">View your profile</a></li>
              <li><a href="<?php echo h(url('/My/Create.aspx')); ?>">Make a T-Shirt, Shirt or Pants</a></li>
            </ul>
<?php } else { ?>
            <div class="BulletRow"><img src="<?php echo h(url('/img/bullet1V2.png')); ?>" class="Bullet" alt="" /><p>Make a free account and get your own profile page.</p></div>
            <div class="BulletRow"><img src="<?php echo h(url('/img/bullet2V2.png')); ?>" class="Bullet" alt="" /><p>Dress up your 3D character with colours, hats and clothes.</p></div>
            <div class="BulletRow"><img src="<?php echo h(url('/img/bullet3V3.png')); ?>" class="Bullet" alt="" /><p>Upload your own T-Shirts, Shirts and Pants and make friends.</p></div>
            <div style="clear:both;text-align:center;padding:6px">
<?php if (REGISTRATION_OPEN) { ?>
              <a class="Button" href="<?php echo h(url('/Login/NewAccount.aspx')); ?>">Sign Up</a>
<?php } ?>
              <a class="Button" href="<?php echo h(url('/Login/Default.aspx')); ?>">Login</a>
            </div>
<?php } ?>
            <p class="Stats"><b><?php echo $memberCount; ?></b> members &middot; <b><?php echo $onlineCount; ?></b> online now</p>
          </div>
        </div>
        <div style="clear:both"></div>
      </div>
    </td>
    <td>&nbsp;&nbsp;&nbsp;</td>
  </tr>
</table>
<?php
page_footer();
