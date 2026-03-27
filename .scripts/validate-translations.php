<?php

declare(strict_types=1);

/**
 * Parse all bundle translation YAML files to catch syntax errors early.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$dir = dirname(__DIR__) . '/src/Resources/translations';
$files = glob($dir . '/*.yaml') ?: [];

foreach ($files as $file) {
    \Symfony\Component\Yaml\Yaml::parseFile($file);
}

echo 'OK: ' . count($files) . " translation file(s) parsed.\n";
