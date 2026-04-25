<?php

declare(strict_types=1);

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../../../running/horde/vendor/autoload.php',
    __DIR__ . '/../../bundle/vendor/autoload.php',
];

$autoloader = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoloader = require_once $path;
        break;
    }
}

if (!$autoloader) {
    fwrite(STDERR, "Could not find Composer autoloader. Run 'composer install' first.\n");
    fwrite(STDERR, "Tried paths:\n");
    foreach ($autoloadPaths as $path) {
        fwrite(STDERR, "  - $path\n");
    }
    exit(1);
}

$wickedRoot = dirname(__DIR__);
$wickedSrc = $wickedRoot . '/src';
$wickedTest = __DIR__;
if (is_dir($wickedSrc) && method_exists($autoloader, 'addPsr4')) {
    $autoloader->addPsr4('Horde\\Wicked\\', $wickedSrc);
    $autoloader->addPsr4('Horde\\Wicked\\Test\\', $wickedTest);
}

$wickedLib = $wickedRoot . '/lib';
if (is_dir($wickedLib) && method_exists($autoloader, 'addClassMap')) {
    $classmap = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wickedLib)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace($wickedLib . '/', '', $file->getPathname());
        $baseName = str_replace(['/', '.php'], ['_', ''], $relative);
        $class = ($baseName === 'Wicked') ? 'Wicked' : 'Wicked_' . $baseName;
        $classmap[$class] = $file->getPathname();
    }
    $autoloader->addClassMap($classmap);
}

date_default_timezone_set('UTC');
