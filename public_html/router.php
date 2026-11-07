<?php
/*
 * Router for PHP's built-in web server, doing what .htaccess does on Apache:
 *
 *   php -S localhost:8000 -t public_html public_html/router.php
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__;

if (preg_match('#^/(App_Code|App_Data)(/|$)#i', $path) || strpos($path, '..') !== false || basename($path) === 'router.php') {
    http_response_code(403);
    exit('Forbidden');
}

function boblox_run($file, $query = array())
{
    foreach ($query as $k => $v) $_GET[$k] = $v;
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim(substr($file, strlen(__DIR__)), '/');
    chdir(dirname($file));
    require $file;
    exit;
}

if ($path === '/' || $path === '') boblox_run($root . '/Default.php');

// static files
if (is_file($root . $path) && !preg_match('/\.php$/', $path)) return false;
if (is_file($root . $path)) boblox_run($root . $path);
if (is_dir($root . $path) && is_file($root . rtrim($path, '/') . '/index.php')) boblox_run($root . rtrim($path, '/') . '/index.php');

if (preg_match('#^/(.+)\.asmx/(\w+)$#', $path, $m) && is_file($root . '/' . $m[1] . '.php')) {
    boblox_run($root . '/' . $m[1] . '.php', array('method' => $m[2]));
}
if (preg_match('#^/(.+)\.(aspx|ashx)$#i', $path, $m) && is_file($root . '/' . $m[1] . '.php')) {
    boblox_run($root . '/' . $m[1] . '.php');
}
if (preg_match('#^/users/(\d+)/profile/?$#', $path, $m)) {
    boblox_run($root . '/User.php', array('ID' => $m[1]));
}
if (preg_match('#^/([A-Za-z0-9_]{3,20})/?$#', $path, $m)) {
    boblox_run($root . '/User.php', array('name' => $m[1]));
}

http_response_code(404);
echo 'Not found';
