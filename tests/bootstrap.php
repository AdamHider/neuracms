<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. Builds a disposable copy of the project so tests never touch
 * the real content/ or var/ folders.
 */

require __DIR__ . '/../vendor/autoload.php';

$fixtureRoot = sys_get_temp_dir() . '/flatcms-tests-' . getmypid();
$GLOBALS['FLATCMS_TEST_ROOT'] = $fixtureRoot;

function flatcms_fixture_root(): string
{
    $root = $GLOBALS['FLATCMS_TEST_ROOT'];
    if (!is_dir($root)) {
        mkdir($root, 0775, true);
    }

    return $root;
}
