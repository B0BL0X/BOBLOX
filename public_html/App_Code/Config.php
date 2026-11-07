<?php
/*
 * BOBLOX site settings. Edit these for your own profile hoster.
 * (This replaces the "edit line 114 / line 198 / line 210" steps of the old static page.)
 */

// Name shown in titles, the header and the footer.
define('SITE_NAME', 'BOBLOX');

// Domain shown in profile links ("marcel's BOBLOX: http://boblox.dev.tc/marcel").
// Leave empty to use the host the visitor typed.
define('SITE_DOMAIN', '');

// If the site lives in a sub folder (http://example.com/boblox/) put '/boblox/' here.
define('BASE_PATH', '/');

// true  = old-school ASP.NET style addresses (/User.aspx?ID=1, /marcel, /users/1/profile)
//         served through .htaccess (Apache mod_rewrite) or router.php (php -S).
// false = plain .php addresses, for hosts without mod_rewrite.
define('FRIENDLY_URLS', true);

// Where the SQLite database, uploads and thumbnails are kept. Must be writable by PHP.
define('DATA_DIR', __DIR__ . '/../App_Data');

// Owner contact links (shown on the home page and in the footer).
define('OWNER_DISCORD_URL', 'https://discord.com/users/1211021666193506457');
define('CONTACT_EMAIL', 'm4r@usa.com');

// Allow new people to sign up. The first account ever created becomes the administrator.
define('REGISTRATION_OPEN', true);

// Uploads (T-Shirts, Shirts, Pants, Faces).
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);

// The "BOBLOX Info" post on the home page.
define('HOME_POST_TITLE', 'About ' . SITE_NAME);
define('HOME_POST_BODY',
    SITE_NAME . " is a dedicated website for profile enthusiasts. Here you can find user profiles, " .
    "build your own blocky character and show it off to your friends.\n\n" .
    SITE_NAME . " is a vibrant profile hoster that lets people express themselves with unique avatars, " .
    "T-Shirts, Shirts, Pants and Hats. Make an account, dress up your character and share your profile link!");
