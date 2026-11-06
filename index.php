<?php

declare(strict_types=1);

/**
 * Fallback entry point for shared hosting that cannot point the document root to
 * public/. Forwards every request to public/index.php.
 */

require __DIR__ . '/public/index.php';
