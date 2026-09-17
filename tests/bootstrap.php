<?php

/**
 * @file
 * PHPUnit bootstrap file for Bioland module tests.
 */

// Load Composer autoloader.
$autoloader = require __DIR__ . '/../vendor/autoload.php';

// Register stub classes namespace.
$autoloader->addPsr4('Drupal\\', __DIR__ . '/stubs/Drupal/');
$autoloader->addPsr4('Drupal\\Component\\', __DIR__ . '/stubs/Drupal/Component/');
$autoloader->addPsr4('Symfony\\Component\\HttpFoundation\\', __DIR__ . '/stubs/Symfony/Component/HttpFoundation/');
$autoloader->addPsr4('Symfony\\Component\\DependencyInjection\\', __DIR__ . '/stubs/Symfony/Component/DependencyInjection/');
$autoloader->addPsr4('GuzzleHttp\\', __DIR__ . '/stubs/GuzzleHttp/');

// Load the Drupal stub class.
require_once __DIR__ . '/stubs/Drupal.php';

// hook_requirements() severity constants, normally defined by Drupal core.
if (!defined('REQUIREMENT_OK')) {
    define('REQUIREMENT_OK', 0);
}
if (!defined('REQUIREMENT_INFO')) {
    define('REQUIREMENT_INFO', -1);
}
if (!defined('REQUIREMENT_WARNING')) {
    define('REQUIREMENT_WARNING', 1);
}
if (!defined('REQUIREMENT_ERROR')) {
    define('REQUIREMENT_ERROR', 2);
}

// Define the t() function if it doesn't exist (used by BiolandMenuLink).
if (!function_exists('t')) {
    /**
     * Stub translation function.
     *
     * @param string $string
     *   The string to translate.
     * @param array $args
     *   Replacement arguments.
     * @param array $options
     *   Options.
     *
     * @return string
     *   The translated string.
     */
    function t($string, array $args = [], array $options = []) {
        foreach ($args as $key => $value) {
            $string = str_replace($key, $value, $string);
        }
        return $string;
    }
}
