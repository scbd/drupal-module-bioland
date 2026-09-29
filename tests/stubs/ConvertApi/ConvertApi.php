<?php

namespace ConvertApi;

/**
 * Minimal stub of convertapi/convertapi-php's ConvertApi for unit tests.
 *
 * Real signature confirmed against ConvertAPI/convertapi-php@master
 * (lib/ConvertApi/ConvertApi.php) on 2026-09-27. BiolandUrlScreenshotService
 * always overrides the adapter with a mock in tests, so this stub is never
 * actually invoked for HTTP - it exists only so class_exists() and the
 * static property assignments resolve.
 */
class ConvertApi
{
    public static $apiCredentials;
    public static $conversionTimeout = 1800;
    public static $uploadTimeout = 1800;
    public static $readTimeout = 1800;

    public static function setApiCredentials($apiCredentials)
    {
        self::$apiCredentials = $apiCredentials;
    }

    public static function convert($toFormat, $params, $fromFormat = null)
    {
        throw new \LogicException('ConvertApi::convert() stub must not be called directly in tests; mock the adapter instead.');
    }
}
