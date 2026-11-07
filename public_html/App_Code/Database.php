<?php
/*
 * SQLite database (App_Data/boblox.db). Created and seeded automatically on first use.
 */

define('ITEM_TSHIRT', 2);
define('ITEM_HAT', 8);
define('ITEM_SHIRT', 11);
define('ITEM_PANTS', 12);
define('ITEM_FACE', 18);

define('SCHEMA_VERSION', 3);

function db()
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        http_response_code(500);
        exit('BOBLOX needs the PHP pdo_sqlite extension.');
    }
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
    if (!is_writable(DATA_DIR)) {
        http_response_code(500);
        exit('BOBLOX: the App_Data folder must be writable by PHP.');
    }

    $pdo = new PDO('sqlite:' . DATA_DIR . '/boblox.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    db_migrate($pdo);
    return $pdo;
}

function db_query($sql, $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_row($sql, $params = array())
{
    $r = db_query($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_all($sql, $params = array())
{
    return db_query($sql, $params)->fetchAll();
}

function db_value($sql, $params = array())
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_migrate(PDO $pdo)
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)');
    $version = (int)$pdo->query("SELECT value FROM meta WHERE key = 'schema'")->fetchColumn();
    if ($version >= SCHEMA_VERSION) return;

    $pdo->beginTransaction();
    if ($version < 1) {
        db_create_tables($pdo);
        db_seed($pdo);
    }
    if ($version < 2) {
        // v2: saved character pose (JSON, see js/Poses.js)
        $pdo->exec("ALTER TABLE users ADD COLUMN pose TEXT NOT NULL DEFAULT ''");
    }
    if ($version < 3) {
        // v3: admin panel (bans, site settings, custom badges, admin log)
        $pdo->exec("
            ALTER TABLE users ADD COLUMN banned INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE users ADD COLUMN ban_reason TEXT NOT NULL DEFAULT '';
            CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS badges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                file TEXT NOT NULL,
                created INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS user_badges (
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                badge_id INTEGER NOT NULL REFERENCES badges(id) ON DELETE CASCADE,
                awarded_by INTEGER NOT NULL DEFAULT 0,
                created INTEGER NOT NULL,
                PRIMARY KEY (user_id, badge_id)
            );
            CREATE TABLE IF NOT EXISTS admin_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                created INTEGER NOT NULL
            );
        ");
    }
    $pdo->prepare("INSERT OR REPLACE INTO meta (key, value) VALUES ('schema', ?)")->execute(array(SCHEMA_VERSION));
    $pdo->commit();
}

function db_create_tables(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            blurb TEXT NOT NULL DEFAULT '',
            discord TEXT NOT NULL DEFAULT '',
            is_admin INTEGER NOT NULL DEFAULT 0,
            created INTEGER NOT NULL,
            last_seen INTEGER NOT NULL DEFAULT 0,
            profile_views INTEGER NOT NULL DEFAULT 0,
            thumb_version INTEGER NOT NULL DEFAULT 1,
            thumb_rendered INTEGER NOT NULL DEFAULT 0,
            color_head INTEGER NOT NULL DEFAULT 6,
            color_torso INTEGER NOT NULL DEFAULT 8,
            color_left_arm INTEGER NOT NULL DEFAULT 6,
            color_right_arm INTEGER NOT NULL DEFAULT 6,
            color_left_leg INTEGER NOT NULL DEFAULT 19,
            color_right_leg INTEGER NOT NULL DEFAULT 19
        );
        CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            creator_id INTEGER NOT NULL DEFAULT 0,
            file TEXT,
            mesh TEXT,
            color TEXT,
            face_slot INTEGER,
            created INTEGER NOT NULL,
            takes INTEGER NOT NULL DEFAULT 0,
            deleted INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS items_type ON items(type, deleted, created);
        CREATE INDEX IF NOT EXISTS items_creator ON items(creator_id);
        CREATE TABLE IF NOT EXISTS inventory (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            created INTEGER NOT NULL,
            PRIMARY KEY (user_id, item_id)
        );
        CREATE TABLE IF NOT EXISTS wearing (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, item_id)
        );
        CREATE TABLE IF NOT EXISTS friends (
            requester_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            target_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            accepted INTEGER NOT NULL DEFAULT 0,
            created INTEGER NOT NULL,
            PRIMARY KEY (requester_id, target_id)
        );
        CREATE INDEX IF NOT EXISTS friends_target ON friends(target_id, accepted);
    ");
}

/** Built-in catalog: hats, faces and a few starter clothes (all made by "BOBLOX"). */
function db_seed(PDO $pdo)
{
    if ((int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn() > 0) return;
    $now = time();
    $ins = $pdo->prepare('INSERT INTO items (type, name, description, creator_id, file, mesh, color, face_slot, created) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?)');

    $hats = array(
        array('tophat', 'Top Hat', 'A tall and fancy hat for tall and fancy people.', '#1b1b1b'),
        array('cone', 'Traffic Cone', 'Caution: wearer may be under construction.', '#f07a1a'),
        array('cap', 'Red Baseball Cap', 'Batter up!', '#c4291c'),
        array('cap', 'Blue Baseball Cap', 'The same cap, but blue.', '#0e6aac'),
        array('party', 'Party Hat', 'Every day is a party.', '#6c337c'),
        array('crown', 'Golden Crown', 'Fit for the king of the bricks.', '#e2b13c'),
        array('viking', 'Viking Helmet', 'Horns sold separately. Just kidding, they are included.', '#8a8d91'),
        array('chef', 'Chef Hat', 'Bon appetit!', '#f7f7f7'),
        array('halo', 'Shiny Halo', 'For the well-behaved.', '#ffe66b'),
        array('cowboy', 'Cowboy Hat', 'Yeehaw.', '#7d5d46'),
        array('beanie', 'Knit Beanie', 'Warm and cozy.', '#0e6aac'),
        array('bucket', 'Bucket Hat', 'Ready for fishing.', '#799182'),
        array('antlers', 'Moose Antlers', 'Majestic.', '#a16035'),
    );
    foreach ($hats as $i => $h) {
        $ins->execute(array(ITEM_HAT, $h[1], $h[2], null, $h[0], $h[3], null, $now - 1000 + $i));
    }

    $faces = array(
        array(0, 'Smile', 'The classic.'),
        array(1, 'Frown', 'Somebody is having a bad day.'),
        array(2, 'Chompy', 'Do not feed after midnight.'),
        array(3, 'No Face', 'Mysterious.'),
    );
    foreach ($faces as $i => $f) {
        $ins->execute(array(ITEM_FACE, $f[1], $f[2], null, null, null, $f[0], $now - 900 + $i));
    }

    $starter = array(
        array(ITEM_TSHIRT, 'BOBLOX Logo Tee', 'Show some love for the site.', 'starter/LogoTee.png'),
        array(ITEM_TSHIRT, 'Smiley Tee', 'Have a cool day :)', 'starter/SmileyTee.png'),
        array(ITEM_SHIRT, 'Blue Collar Shirt', 'A smart shirt for smart builders.', 'starter/BlueCollarShirt.png'),
        array(ITEM_SHIRT, 'Black Hoodie', 'Comfy.', 'starter/BlackHoodie.png'),
        array(ITEM_PANTS, 'Blue Jeans', 'Classic denim.', 'starter/BlueJeans.png'),
        array(ITEM_PANTS, 'Black Slacks', 'Goes with everything.', 'starter/BlackSlacks.png'),
    );
    foreach ($starter as $i => $s) {
        $ins->execute(array($s[0], $s[1], $s[2], $s[3], null, null, null, $now - 800 + $i));
    }
}
