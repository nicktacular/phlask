<?php

$dot = dirname(__FILE__);

if (!file_exists($composer = dirname($dot) . '/vendor/autoload.php')) {
    die("Composer install necessary. Please run `composer install` first.");
}

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = include $composer;
$autoloader->add('phlaskTest\\', $dot);

// Where the fixtures at, YO!?
define('FIXTURES_DIR', $dot . '/fixtures');
