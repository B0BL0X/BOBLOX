<?php
/*
 * Tiny "ASMX" style JSON web service runner, in the shape ASP.NET AJAX used:
 *   POST /WebServices/Character.asmx/WearItem   body: {"itemId": 12}
 *   200 -> {"d": <result>}
 *   4xx -> {"Message": "...", "ExceptionType": "..."}
 */
require_once __DIR__ . '/Bootstrap.php';

class WebServiceException extends Exception
{
    public $status;

    public function __construct($message, $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function ws_fail($message, $status = 400)
{
    throw new WebServiceException($message, $status);
}

function ws_user()
{
    $u = current_user();
    if (!$u) ws_fail('You need to be logged in to do that.', 401);
    return $u;
}

function ws_int($params, $key)
{
    if (!isset($params[$key]) || !is_numeric($params[$key])) ws_fail('Missing parameter: ' . $key);
    return (int)$params[$key];
}

function ws_string($params, $key, $default = null)
{
    if (!isset($params[$key])) {
        if ($default !== null) return $default;
        ws_fail('Missing parameter: ' . $key);
    }
    if (!is_string($params[$key])) ws_fail('Bad parameter: ' . $key);
    return $params[$key];
}

/**
 * $methods = array('MethodName' => array('handler' => callable, 'get' => bool))
 * Every method accepts POST with a JSON body and needs the CSRF header.
 * Methods marked 'get' => true are read-only and may also be called with GET.
 */
function ws_run($methods)
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    try {
        $name = isset($_GET['method']) && is_string($_GET['method']) ? $_GET['method'] : '';
        if (!isset($methods[$name])) ws_fail('Unknown web method "' . $name . '".', 404);
        $method = $methods[$name];

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($method['get'])) {
            $params = $_GET;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
            if (!csrf_valid($token)) ws_fail('Your session expired. Please refresh the page.', 403);
            $raw = file_get_contents('php://input');
            if (strlen($raw) > 4 * 1024 * 1024) ws_fail('Request too large.', 413);
            $params = $raw === '' ? array() : json_decode($raw, true);
            if (!is_array($params)) ws_fail('Bad JSON.');
        } else {
            ws_fail('Method not allowed.', 405);
        }

        $result = call_user_func($method['handler'], $params);
        echo json_encode(array('d' => $result));
    } catch (WebServiceException $e) {
        http_response_code($e->status);
        echo json_encode(array('Message' => $e->getMessage(), 'ExceptionType' => 'WebServiceException'));
    } catch (Exception $e) {
        error_log('BOBLOX web service error: ' . $e);
        http_response_code(500);
        echo json_encode(array('Message' => 'There was an error processing the request.', 'ExceptionType' => 'Exception'));
    }
}
