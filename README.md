# BOBLOX – a 2006-style profile hoster

BOBLOX is a fan-made profile hoster with an old-school look. People sign up, get their own profile page
(`/yourname`), dress up a blocky 3D character and make friends.

It started as a single static HTML page (`src.zip`, kept for reference). It is now a real website.

## Features

- **Accounts**: sign up, log in and out, change your password. The **first account ever created becomes the administrator**.
- **Profiles** at `/User.aspx?ID=1`, `/users/1/profile` or simply `/marcel`. They show the character picture (with a
  *View in 3D* button), the blurb, a Discord link, badges, statistics (joined, friends, profile views, items
  created, last online), friends, what the person is wearing and what they made.
- **Character page** (`/My/Character.aspx`), using the **2006 figure model** (`tools/figure/2006Figure.fbx`):
  - recolour each body part (head, torso, both arms, both legs) with the 32 colours of the figure's texture,
    or paint everything one colour,
  - wear **Hats**, **Faces**, **T-Shirts**, **Shirts** and **Pants** from your wardrobe,
  - live 3D preview you can turn with the mouse; every change saves by itself over AJAX and
    updates the picture shown on your profile.
- **Catalog** (`/Catalog.aspx`): 13 hats, 4 faces and some starter clothes, all free. Search and sort by newest / most taken.
- **Create** (`/My/Create.aspx`): upload your own T-Shirts, Shirts, Pants (585×559 template, downloadable on the page) and Faces.
- **Friends**: send, accept, decline and remove friend requests; alerts in the header.
- **People** (`/Browse.aspx`): find users.
- Admins (and the creator) can delete catalog items.

## The 2006 stack

Like the 2006 site, pages use ASP.NET-style addresses (`.aspx` pages, `.ashx` image handlers and `.asmx` web
services), table-and-panel XHTML layouts, the original `AllCSS.css`, and AJAX calls made with a hand-written
`XMLHttpRequest` wrapper that talks JSON to `WebServices/*.asmx/<Method>` and gets ASP.NET AJAX style
`{"d": ...}` answers (`js/Ajax.js`). Character pictures are PNG thumbnails served by `Thumbs/Avatar.ashx`.

Behind those addresses the server code is **PHP with SQLite**, so it runs on ordinary `public_html` hosting
(the original ASP.NET needs Windows/IIS). The 3D preview uses three.js (WebGL), which did not exist in 2006,
because it is the only way to show the real figure model in a browser.

## Running it

Requirements: PHP 7.4 or newer with the `pdo_sqlite` and `gd` extensions.

### On shared hosting (Apache, `public_html`)

1. Upload everything inside `public_html/` to your `public_html` folder.
2. Make sure `public_html/App_Data/` is writable by PHP (the database, uploads and thumbnails go there; `.htaccess`
   keeps it private).
3. Open the site and sign up. Your account is the administrator.

`.htaccess` needs `mod_rewrite` for the `.aspx` addresses and `/username` links. If your host does not have it,
set `FRIENDLY_URLS` to `false` in `App_Code/Config.php` and the site will use plain `.php` addresses instead.

### On your own computer

```sh
php -S localhost:8000 -t public_html public_html/router.php
```

Then open http://localhost:8000/.

## Making it yours

Edit `public_html/App_Code/Config.php`:

| Setting | What it does |
| --- | --- |
| `SITE_NAME` | Name shown everywhere |
| `SITE_DOMAIN` | Domain shown in profile links (empty = the current host) |
| `BASE_PATH` | `/` or a sub folder such as `/boblox/` (also change `RewriteBase` in `.htaccess`) |
| `OWNER_DISCORD_URL`, `CONTACT_EMAIL` | Owner links on the home page and footer |
| `HOME_POST_TITLE`, `HOME_POST_BODY` | The "BOBLOX Info" post on the home page |
| `REGISTRATION_OPEN` | Turn sign ups on or off |

## Files

```
public_html/
  Default.aspx (.php)      home page ("BOBLOX Info" thread, newest members)
  User.aspx                profiles
  Browse.aspx              people search
  Catalog.aspx, Item.aspx  catalog
  Login/                   login and sign up
  My/                      My BOBLOX, Character, Create, account settings
  WebServices/*.asmx       AJAX web services (Character, Catalog, Friends, Users)
  Thumbs/*.ashx            avatar and item pictures
  Asset/                   the figure (2006Figure.json + textures), item textures, starter clothes
  App_Code/                server code (config, database, avatars, layout)
  App_Data/                database, uploads, thumbnails (created at runtime, private)
  js/                      Ajax.js, Site.js, Character.js, Avatar3D.js, Hats.js, BrickColors.js, three.js
  css/                     AllCSS.css (original) + Boblox.css
tools/
  figure/2006Figure.fbx    the figure model
  figure/convert.mjs       turns the FBX into Asset/Figure/2006Figure.json (npm run convert-figure)
  render-hats.cjs          renders the hat pictures in img/Hats
```

### Adding hats

Hats are built from simple shapes in `public_html/js/Hats.js`. Add a builder there, add it to the list in
`App_Code/Database.php` (`db_seed`, used on a fresh database; on an existing site insert a row into `items`
with `type = 8` and the `mesh` key), then run `tools/render-hats.cjs` to make its catalog picture.
