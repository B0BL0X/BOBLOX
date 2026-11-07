<?php
/*
 * Small HTML / URL helpers.
 */

/** HTML-escape a value. */
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build a site URL from an ASP.NET style path, e.g. url('/User.aspx?ID=1').
 * With FRIENDLY_URLS off, '.aspx' / '.ashx' become '.php' and
 * '/X.asmx/Method' becomes '/X.php?method=Method'.
 */
function url($path)
{
    $path = ltrim($path, '/');
    if (!FRIENDLY_URLS) {
        $path = preg_replace_callback('#^([^?]*?)\.asmx/(\w+)(\?(.*))?$#', function ($m) {
            return $m[1] . '.php?method=' . $m[2] . (isset($m[4]) && $m[4] !== '' ? '&' . $m[4] : '');
        }, $path);
        $path = preg_replace('#\.(aspx|ashx)(?=$|\?|\#)#', '.php', $path);
        // vanity / pretty profile links
        $path = preg_replace('#^users/(\d+)/profile/?$#', 'User.php?ID=$1', $path);
    }
    return BASE_PATH . $path;
}

/** Profile link for a user row (or id). */
function profile_url($userId)
{
    return url('/User.aspx?ID=' . (int)$userId);
}

/** Absolute profile address shown on the profile ("marcel's BOBLOX: ..."). */
function vanity_url($username)
{
    $host = SITE_DOMAIN !== '' ? SITE_DOMAIN : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = FRIENDLY_URLS ? BASE_PATH . rawurlencode($username) : BASE_PATH . 'User.php?name=' . rawurlencode($username);
    return $scheme . '://' . $host . $path;
}

/** Redirect to a URL built with url() (or returned by safe_return_url()). */
function redirect($location)
{
    header('Location: ' . $location);
    exit;
}

/** Only allow local return URLs (no open redirects). */
function safe_return_url($value, $fallback)
{
    if (is_string($value) && $value !== '' && $value[0] === '/' && (strlen($value) < 2 || ($value[1] !== '/' && $value[1] !== '\\'))) {
        return $value;
    }
    return url($fallback);
}

function format_date($ts)
{
    return $ts ? date('n/j/Y', (int)$ts) : 'never';
}

function format_datetime($ts)
{
    return $ts ? date('j M Y g:i A', (int)$ts) : 'never';
}

function time_ago($ts)
{
    $d = time() - (int)$ts;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' minute' . (floor($d / 60) == 1 ? '' : 's') . ' ago';
    if ($d < 86400) return floor($d / 3600) . ' hour' . (floor($d / 3600) == 1 ? '' : 's') . ' ago';
    if ($d < 86400 * 30) return floor($d / 86400) . ' day' . (floor($d / 86400) == 1 ? '' : 's') . ' ago';
    return format_date($ts);
}

/** Request helpers */
function get_int($key, $default = 0)
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) && preg_match('/^\d+$/', (string)$_GET[$key]) ? (int)$_GET[$key] : $default;
}

function get_str($key, $default = '')
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $default;
}

function post_str($key, $default = '')
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : $default;
}

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Classic numbered pager. */
function render_pager($page, $totalPages, $makeUrl)
{
    if ($totalPages <= 1) return '';
    $out = '<div class="Pager">';
    if ($page > 1) $out .= '<a href="' . h($makeUrl($page - 1)) . '">&laquo; Previous</a> ';
    $out .= '<span>Page ' . (int)$page . ' of ' . (int)$totalPages . '</span>';
    if ($page < $totalPages) $out .= ' <a href="' . h($makeUrl($page + 1)) . '">Next &raquo;</a>';
    return $out . '</div>';
}

function multiline($text)
{
    return nl2br(h($text));
}
