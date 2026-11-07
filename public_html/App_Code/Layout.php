<?php
/*
 * The master page (header, navigation, footer) and shared bits of markup.
 */

function page_header($title, $options = array())
{
    $user = current_user();
    $styles = isset($options['styles']) ? $options['styles'] : array();
    $scripts = isset($options['scripts']) ? $options['scripts'] : array();
    $three = !empty($options['three']);
    $requests = $user ? friend_request_count($user['id']) : 0;
    $config = array(
        'root' => BASE_PATH,
        'friendlyUrls' => FRIENDLY_URLS,
        'csrf' => csrf_token(),
        'userId' => $user ? (int)$user['id'] : 0,
    );
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" id="www-boblox-com">
<head>
    <title><?php echo h($title === '' ? SITE_NAME . ': A Cool Profile Hoster with Avatars' : $title . ' - ' . SITE_NAME); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=920" />
    <meta name="author" content="<?php echo h(SITE_NAME); ?>" />
    <meta name="keywords" content="profile, avatar, character, profile hoster, blocky, t-shirts, hats" />
    <link id="ctl00_Imports" rel="stylesheet" type="text/css" href="<?php echo h(url('/css/AllCSS.css')); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo h(url('/css/Boblox.css')); ?>" />
<?php foreach ($styles as $s) { ?>
    <link rel="stylesheet" type="text/css" href="<?php echo h(url($s)); ?>" />
<?php } ?>
    <link id="ctl00_Favicon" rel="shortcut icon" type="image/png" href="<?php echo h(url('/img/favicon.png')); ?>" />
    <script type="text/javascript">
    //<![CDATA[
    var BOBLOX = window.BOBLOX || {}; BOBLOX.Config = <?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    //]]>
    </script>
    <script src="<?php echo h(url('/js/Ajax.js')); ?>" type="text/javascript"></script>
    <script src="<?php echo h(url('/js/Site.js')); ?>" type="text/javascript"></script>
<?php if ($three) { ?>
    <script src="<?php echo h(url('/js/three/three.min.js')); ?>" type="text/javascript"></script>
    <script src="<?php echo h(url('/js/BrickColors.js')); ?>" type="text/javascript"></script>
    <script src="<?php echo h(url('/js/Hats.js')); ?>" type="text/javascript"></script>
    <script src="<?php echo h(url('/js/Avatar3D.js')); ?>" type="text/javascript"></script>
<?php } ?>
<?php foreach ($scripts as $s) { ?>
    <script src="<?php echo h(url($s)); ?>" type="text/javascript"></script>
<?php } ?>
</head>
<body>
<div id="Container">
    <div id="Header">
        <div id="Banner">
            <div id="Options">
                <div id="Authentication">
<?php if ($user) { ?>
                    <span>Logged in as <a href="<?php echo h(profile_url($user['id'])); ?>"><?php echo h($user['username']); ?></a></span>
                    <span>&nbsp;|&nbsp;</span>
                    <a href="#" onclick="BOBLOX.logout(); return false;">Logout</a>
<?php } else { ?>
                    <a href="<?php echo h(url('/Login/Default.aspx')); ?>">Login</a>
<?php     if (REGISTRATION_OPEN) { ?>
                    <span>&nbsp;|&nbsp;</span>
                    <a href="<?php echo h(url('/Login/NewAccount.aspx')); ?>">Sign Up</a>
<?php     } ?>
<?php } ?>
                </div>
<?php if ($user) { ?>
                <div id="Settings"><a href="<?php echo h(url('/My/Profile.aspx')); ?>">Account Settings</a></div>
<?php } ?>
            </div>
            <div id="Logo"><a id="ctl00_rbxImage_Logo" title="<?php echo h(SITE_NAME); ?>" href="<?php echo h(url('/Default.aspx')); ?>" style="display:inline-block;cursor:pointer;"><img style="width:236px;height:72px;" src="<?php echo h(url('/img/boblox.png')); ?>" border="0" alt="<?php echo h(SITE_NAME); ?>" class="thebannerlogo" /></a></div>
            <div id="Alerts">
                <table style="width:100%;height:100%">
                    <tr>
                        <td valign="middle">
<?php if ($user && $requests > 0) { ?>
                            <div id="AlertSpace">
                                <div id="MessageAlert"><a href="<?php echo h(url('/My/Home.aspx')); ?>"><?php echo (int)$requests; ?> Friend Request<?php echo $requests === 1 ? '' : 's'; ?></a></div>
                            </div>
<?php } elseif (!$user && REGISTRATION_OPEN) { ?>
                            <div id="AlertSpace" class="SignUpAndPlay"><a href="<?php echo h(url('/Login/NewAccount.aspx')); ?>" style="color:#6e99c9">Make your character!</a></div>
<?php } ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="Navigation">
            <span><a id="ctl00_hlHome" class="MenuItem" href="<?php echo h(url('/Default.aspx')); ?>">Home</a></span>
            <span class="Separator">&nbsp;|&nbsp;</span>
<?php if ($user) { ?>
            <span><a id="ctl00_hlMyBoblox" class="MenuItem" href="<?php echo h(url('/My/Home.aspx')); ?>">My <?php echo h(SITE_NAME); ?></a></span>
            <span class="Separator">&nbsp;|&nbsp;</span>
            <span><a id="ctl00_hlCharacter" class="MenuItem" href="<?php echo h(url('/My/Character.aspx')); ?>">Character</a></span>
            <span class="Separator">&nbsp;|&nbsp;</span>
<?php } ?>
            <span><a id="ctl00_hlCatalog" class="MenuItem" href="<?php echo h(url('/Catalog.aspx')); ?>">Catalog</a></span>
            <span class="Separator">&nbsp;|&nbsp;</span>
            <span><a id="ctl00_hlBrowse" class="MenuItem" href="<?php echo h(url('/Browse.aspx')); ?>">People</a></span>
<?php if ($user) { ?>
            <span class="Separator">&nbsp;|&nbsp;</span>
            <span><a id="ctl00_hlCreate" class="MenuItem" href="<?php echo h(url('/My/Create.aspx')); ?>">Create</a></span>
<?php } ?>
        </div>
    </div>
    <div id="Body">
<?php
}

function page_footer()
{
    ?>
    </div>
    <div style="clear:both;height:0;"></div>
    <div id="Footer">
        <hr />
        <p class="Legalese">
            <?php echo h(SITE_NAME); ?>, "A Cool Profile Hoster". A fan-made profile hoster with an old-school look.
            <br />
            <?php echo h(SITE_NAME); ?> is not affiliated with ROBLOX Corporation, Lego, MegaBloks, Bionicle, Pokemon, Nintendo, Lincoln Logs, Yu Gi Oh, K'nex, Tinkertoys, Erector Set, or the Pirates of the Caribbean. ARrrr!
            <br />
            Everything people upload belongs to them. Be nice.
            <br />
            <a href="<?php echo h(url('/Default.aspx')); ?>">About Us</a>
<?php if (CONTACT_EMAIL !== '') { ?>
            &nbsp;|&nbsp; <a href="mailto:<?php echo h(CONTACT_EMAIL); ?>">Contact Us</a>
<?php } ?>
        </p>
    </div>
</div>
</body>
</html>
<?php
}

// ---------------------------------------------------------------------------
// Shared fragments
// ---------------------------------------------------------------------------

function avatar_image($user, $size, $attrs = '')
{
    return '<img src="' . h(avatar_thumb_url($user, $size)) . '" width="' . (int)$size . '" height="' . (int)$size . '" alt="' . h($user['username']) . '" title="' . h($user['username']) . '" border="0" ' . $attrs . ' />';
}

function online_label($user)
{
    return is_online($user)
        ? '<span class="UserOnlineMessage">[ Online ]</span>'
        : '<span class="UserOfflineMessage">[ Offline ]</span>';
}

/** Catalog / wardrobe tile. $actions is raw HTML placed under the name. */
function item_tile($item, $actions = '', $size = 110)
{
    $out = '<div class="Asset">';
    $out .= '<div class="AssetThumbnail"><a href="' . h(item_url($item)) . '" title="' . h($item['name']) . '"><img src="' . h(item_thumb_url($item, $size)) . '" width="' . (int)$size . '" height="' . (int)$size . '" alt="' . h($item['name']) . '" border="0" /></a></div>';
    $out .= '<div class="AssetDetails">';
    $out .= '<div class="AssetName"><a href="' . h(item_url($item)) . '">' . h($item['name']) . '</a></div>';
    if (isset($item['creator_id'])) {
        $creator = (int)$item['creator_id'] === 0 ? h(SITE_NAME) : '<a href="' . h(profile_url($item['creator_id'])) . '">' . h(item_creator_name($item)) . '</a>';
        $out .= '<div class="AssetCreator"><span class="Label">Creator:</span> ' . $creator . '</div>';
    }
    $out .= $actions;
    $out .= '</div></div>';
    return $out;
}

/** Badges a user has earned. */
function user_badges($user)
{
    $badges = array();
    if ((int)$user['is_admin'] === 1) {
        $badges[] = array('Administrator', 'img/bobloxadmin.png', 'This badge is given to administrators on the site.');
    }
    $created = (int)db_value('SELECT COUNT(*) FROM items WHERE creator_id = ? AND deleted = 0', array((int)$user['id']));
    if ($created >= 1) {
        $badges[] = array('Homestead', 'img/Homestead.webp', 'This badge is given to people who have made an item for the catalog.');
    }
    if ($created >= 5) {
        $badges[] = array('Bricksmith', 'img/Supermod-0.webp', 'This badge is given to people who have made 5 or more catalog items.');
    }
    if (friend_count($user['id']) >= 5) {
        $badges[] = array('Friendship', 'img/Ambassador-256x256.webp', 'This badge is given to people who have 5 or more friends.');
    }
    if (time() - (int)$user['created'] >= 365 * 86400) {
        $badges[] = array('Veteran', 'img/Badge_combat.webp', 'This badge is given to people who have been members for a year or more.');
    }
    return $badges;
}
