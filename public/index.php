<?php

declare(strict_types=1);

/**
 * Single entry point of FlatCMS. Everything outside public/ stays unreachable.
 */

use FlatCms\App;

define('FLATCMS_ROOT', dirname(__DIR__));
define('FLATCMS_START', microtime(true));

$autoload = FLATCMS_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Dependencies are missing. Run "composer install" or upload the release archive that includes vendor/.';
    exit;
}

require $autoload;

$app = new App(FLATCMS_ROOT);

// Templates get the $app variable.
$GLOBALS['flatcms'] = $app;

$app->run();
