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

// Load the Drupal stub class.
require_once __DIR__ . '/stubs/Drupal.php';

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
