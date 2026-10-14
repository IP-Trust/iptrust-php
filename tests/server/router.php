<?php

/*
 * Router for PHP's built-in web server, used by CurlTransportTest to exercise
 * the real cURL transport. Start with:
 *
 *   php -S 127.0.0.1:<port> tests/server/router.php
 */

declare(strict_types=1);

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$body = file_get_contents("php://input");

$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, "HTTP_") === 0) {
        $name = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($k, 5)))));
        $headers[$name] = $v;
    }
}
if (isset($_SERVER["CONTENT_TYPE"])) {
    $headers["Content-Type"] = $_SERVER["CONTENT_TYPE"];
}

if (strpos($path, "/echo") === 0) {
    header("Content-Type: application/json");
    echo json_encode([
        "method" => $_SERVER["REQUEST_METHOD"],
        "path" => $path,
        "uri" => $_SERVER["REQUEST_URI"],
        "headers" => $headers,
        "body" => $body,
    ]);
    return true;
}

if (preg_match('#^/status/(\d{3})$#', $path, $m)) {
    http_response_code((int) $m[1]);
    header("Content-Type: application/json");
    echo json_encode(["error" => ["error_code" => "test_error", "detail" => "status " . $m[1]]]);
    return true;
}

if ($path === "/slow") {
    usleep(1500000);
    echo "{}";
    return true;
}

if ($path === "/file") {
    header("Content-Type: application/octet-stream");
    echo str_repeat("abc", 1000);
    return true;
}

if ($path === "/file-forbidden") {
    http_response_code(403);
    echo "expired";
    return true;
}

http_response_code(404);
echo "not found";
return true;
