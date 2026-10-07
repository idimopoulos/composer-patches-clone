<?php

/**
 * Router for the fixture patch server (php -S).
 *
 * Files under /private/ require HTTP basic auth (user / secret); everything
 * else is served as a static file.
 */

declare(strict_types=1);

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($path, '/private/')) {
    if (($_SERVER['PHP_AUTH_USER'] ?? null) !== 'user' || ($_SERVER['PHP_AUTH_PW'] ?? null) !== 'secret') {
        header('WWW-Authenticate: Basic realm="patches"');
        http_response_code(401);
        echo 'Unauthorized';

        return true;
    }
}

return false;
