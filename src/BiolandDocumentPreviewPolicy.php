<?php

namespace Drupal\bioland;

use Drupal\Core\Site\Settings;

/**
 * Pure decision helpers for the document-preview feature (BL-1192).
 *
 * Converter map (checked 2026-09-27 via GET
 * https://v2.convertapi.com/info/openapi/{ext}/to/webp for every allowed
 * extension): direct (a {ext}/to/webp converter exists) is pdf, docx, pptx,
 * xlsx; everything else is routed via-pdf per the item brief's explicit
 * fallback instruction. Spot checks of {ext}/to/pdf found doc, ppt, xls,
 * fodt, fods, fodp, fodg, key, numbers, pages ALSO 404 there (their real
 * route likely needs an office-format intermediate, not pdf) - a known,
 * deferred gap; see the coder report.
 */
class BiolandDocumentPreviewPolicy
{
    public const DOCUMENT_FIELD = 'field_media_document';
    public const IMAGE_FIELD = 'field_media_image';
    public const PREVIEW_URI_PREFIX = 'public://bioland/document-previews/';
    public const ROUTE_DIRECT = 'direct';
    public const ROUTE_VIA_PDF = 'via-pdf';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_TRANSIENT = 'transient';
    public const STATUS_PERMANENT = 'permanent';
    /** Mirrors Drupal core's ImageItem field max lengths. */
    public const ALT_MAX_LENGTH = 512;
    public const TITLE_MAX_LENGTH = 1024;

    private const DIRECT_EXTENSIONS = ['pdf', 'docx', 'pptx', 'xlsx'];
    private const VIA_PDF_EXTENSIONS = [
        'txt', 'rtf', 'doc', 'ppt', 'xls', 'odf', 'odg', 'odp', 'ods', 'odt',
        'fodt', 'fods', 'fodp', 'fodg', 'key', 'numbers', 'pages',
    ];

    /** Whether a document-preview conversion should be considered at all. */
    public static function qualifies(string $entityType, string $bundle, bool $hasDocument, bool $enabled, bool $hasSecret): bool
    {
        return $entityType === 'media' && $bundle === 'document' && $hasDocument && $enabled && $hasSecret;
    }

    /** Whether an update replaced the document file. */
    public static function documentChanged(?int $originalFid, ?int $newFid): bool
    {
        return $originalFid === null || $originalFid !== $newFid;
    }

    /** Whether the given Image field URI was written by this module. */
    public static function isModuleOwnedImage(?string $uri): bool
    {
        return !empty($uri) && strpos($uri, self::PREVIEW_URI_PREFIX) === 0;
    }

    /** Resolves the conversion route for an extension, or NULL when unsupported. */
    public static function converterFor(string $extension): ?string
    {
        $extension = strtolower($extension);
        if (in_array($extension, self::DIRECT_EXTENSIONS, true)) {
            return self::ROUTE_DIRECT;
        }
        if ($extension !== '' && in_array($extension, self::VIA_PDF_EXTENSIONS, true)) {
            return self::ROUTE_VIA_PDF;
        }
        return null;
    }

    /**
     * Builds the ConvertAPI parameter array/arrays for a route. PageRange is
     * the cost guard and is never dropped. Direct is one call; via-pdf is
     * two (StoreFile on step 1 so step 2 can chain it, not re-download it).
     *
     * @return array[]
     */
    public static function buildParams(string $route): array
    {
        $imageParams = [
            'PageRange' => '1', 'ImageWidth' => 1024, 'ImageQuality' => 75,
            'ScaleProportions' => true, 'StoreFile' => false,
        ];

        if ($route === self::ROUTE_DIRECT) {
            return [$imageParams];
        }

        return [['PageRange' => '1', 'StoreFile' => true], $imageParams];
    }

    /** Extensions whose direct {ext}/to/webp converter does not document ScaleProportions. */
    public static function dropsScaleProportions(string $extension): bool
    {
        return in_array(strtolower($extension), ['docx', 'pptx', 'xlsx'], true);
    }

    /** Image field alt text: "First page of {name}", cleaned and truncated. */
    public static function altText(string $mediaName): string
    {
        return self::truncate('First page of ' . self::cleanText($mediaName), self::ALT_MAX_LENGTH);
    }

    /** Image field title text from the media name, cleaned and truncated. */
    public static function titleText(string $mediaName): string
    {
        return self::truncate(self::cleanText($mediaName), self::TITLE_MAX_LENGTH);
    }

    /** Classifies a ConvertAPI status/error code for retry purposes. */
    public static function classifyStatus(int $statusCode): string
    {
        if ($statusCode === 200) {
            return self::STATUS_SUCCESS;
        }
        if ($statusCode === 429 || ($statusCode >= 500 && $statusCode < 600)) {
            return self::STATUS_TRANSIENT;
        }
        return self::STATUS_PERMANENT;
    }

    /**
     * Resolves CONVERT_API_SECRET: environment first, then Settings
     * fallback. Never stored in config, never logged.
     */
    public static function resolveSecret(): string
    {
        $env = getenv('CONVERT_API_SECRET');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        $setting = Settings::get('bioland_convert_api_secret');
        return is_string($setting) ? $setting : '';
    }

    private static function cleanText(string $text): string
    {
        return trim(strip_tags($text));
    }

    private static function truncate(string $text, int $maxLength): string
    {
        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength) : $text;
    }
}
