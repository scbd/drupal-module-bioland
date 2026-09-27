<?php

namespace Drupal\bioland;

use Drupal\Core\Site\Settings;

/**
 * Pure decision helpers for the document-preview feature (BL-1192).
 *
 * Converter map re-verified 2026-09-27 (fix cycle after review finding #1),
 * against live ConvertAPI endpoints:
 * - Direct (GET https://v2.convertapi.com/info/openapi/{ext}/to/webp = 200):
 *   pdf, docx, pptx, xlsx. See https://www.convertapi.com/docx-to-webp
 *   (PageRange + ImageQuality documented, ScaleProportions is not).
 * - Via-pdf (no direct webp route, but {ext}/to/pdf = 200; then pdf/to/webp):
 *   odt, ods, odp, odf, odg, txt, rtf. See https://www.convertapi.com/odt-to-pdf
 *   and siblings.
 * - Via-office (no direct webp or pdf route, but an office-format upconvert
 *   exists; then {office}/to/webp): doc->docx, ppt->pptx, xls->xlsx,
 *   key->pptx, numbers->xlsx, pages->docx. Verified live: GET
 *   https://v2.convertapi.com/info/openapi/doc/to/docx (200), .../ppt/to/pptx
 *   (200), .../xls/to/xlsx (200), .../key/to/pptx (200), .../numbers/to/xlsx
 *   (200), .../pages/to/docx (200). None of these six intermediate
 *   conversions document PageRange (confirmed against
 *   https://www.convertapi.com/doc-to-docx, /ppt-to-pptx, /xls-to-xlsx,
 *   /pages-to-docx, /key-to-pptx, /numbers-to-xlsx - each lists only File[,
 *   Password] + StoreFile), so PageRange is applied only on their second
 *   (webp) step, same as the direct route.
 * - Dropped, no verified route on any tested target (webp/pdf/docx/pptx/xlsx
 *   all 404, including their own un-flattened ODF counterpart, e.g.
 *   fodt/to/odt): fodt, fods, fodp, fodg. These are skipped at enqueue time
 *   with a notice log naming the extension (see
 *   BiolandDocumentPreviewService::enqueueIfNeeded()).
 */
class BiolandDocumentPreviewPolicy
{
    public const DOCUMENT_FIELD = 'field_media_document';
    public const IMAGE_FIELD = 'field_media_image';
    public const PREVIEW_URI_PREFIX = 'public://bioland/document-previews/';
    public const ROUTE_DIRECT = 'direct';
    public const ROUTE_VIA_PDF = 'via-pdf';
    public const ROUTE_VIA_OFFICE = 'via-office';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_TRANSIENT = 'transient';
    public const STATUS_PERMANENT = 'permanent';
    /** Mirrors Drupal core's ImageItem field max lengths. */
    public const ALT_MAX_LENGTH = 512;
    public const TITLE_MAX_LENGTH = 1024;

    private const DIRECT_EXTENSIONS = ['pdf', 'docx', 'pptx', 'xlsx'];
    private const VIA_PDF_EXTENSIONS = ['txt', 'rtf', 'odf', 'odg', 'odp', 'ods', 'odt'];
    /** Extension => intermediate office format, each verified to accept a direct {intermediate}/to/webp call. */
    private const VIA_OFFICE_EXTENSIONS = [
        'doc' => 'docx',
        'ppt' => 'pptx',
        'xls' => 'xlsx',
        'key' => 'pptx',
        'numbers' => 'xlsx',
        'pages' => 'docx',
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
        if ($extension !== '' && isset(self::VIA_OFFICE_EXTENSIONS[$extension])) {
            return self::ROUTE_VIA_OFFICE;
        }
        if ($extension !== '' && in_array($extension, self::VIA_PDF_EXTENSIONS, true)) {
            return self::ROUTE_VIA_PDF;
        }
        return null;
    }

    /** The intermediate office format for a ROUTE_VIA_OFFICE extension, or NULL. */
    public static function officeIntermediateFor(string $extension): ?string
    {
        return self::VIA_OFFICE_EXTENSIONS[strtolower($extension)] ?? null;
    }

    /**
     * Builds the ConvertAPI parameter array/arrays for a route. PageRange is
     * the cost guard and is never dropped from a step that documents it.
     * Direct is one call. Via-pdf and via-office are both two chained calls
     * (StoreFile on step 1 so step 2 can chain the stored file, never
     * re-downloading it); via-pdf's step 1 (to pdf) documents PageRange and
     * carries it, via-office's step 1 (to docx/pptx/xlsx) does not document
     * PageRange for any of its six extensions and never carries it - the
     * page restriction is still enforced on step 2 (the webp render).
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

        if ($route === self::ROUTE_VIA_OFFICE) {
            return [['StoreFile' => true], $imageParams];
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
