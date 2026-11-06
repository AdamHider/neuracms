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

// Register PSR-4 autoloaders for the project modules so tests can instantiate
// module services without booting the whole application first.
foreach (glob(__DIR__ . '/../modules/*/module.yaml') ?: [] as $manifestFile) {
    $manifest = Symfony\Component\Yaml\Yaml::parseFile($manifestFile);
    $namespace = is_array($manifest) ? ($manifest['namespace'] ?? null) : null;
    if (!is_string($namespace) || $namespace === '') {
        continue;
    }

    $prefix = rtrim($namespace, '\\') . '\\';
    $base = dirname($manifestFile) . '/src';

    spl_autoload_register(static function (string $class) use ($prefix, $base): void {
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $base . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}
