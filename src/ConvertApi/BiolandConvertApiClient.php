<?php

namespace Drupal\bioland\ConvertApi;

use ConvertApi\ConvertApi;
use ConvertApi\Error\Api as ConvertApiError;

/**
 * Thin adapter over the ConvertApi\ConvertApi static SDK client.
 *
 * The static SDK is touched in this file only, so BiolandUrlScreenshotService
 * can be unit tested with a mock of this class instead of the SDK itself.
 * Exactly two calls per screenshot: HTML->JPG, then JPG->WebP chained by the
 * stored-file URL (never re-downloaded/re-uploaded).
 *
 * Parameter names verified live against
 * https://www.convertapi.com/html-to-jpg on 2026-09-27: the documented
 * "viewport" concept IS ImageWidth/ImageHeight (there is no separate
 * ViewportWidth/ViewportHeight parameter, and no "full page" toggle - the
 * rendered image is exactly the requested width/height, i.e. the first
 * screen only, which is what this ticket needs).
 *
 * SDK class names confirmed against ConvertAPI/convertapi-php@master on
 * 2026-09-27: the client is \ConvertApi\ConvertApi (not the three-segment
 * \ConvertApi\ConvertApi\ConvertApi the ticket text originally named), and
 * HTTP-carrying errors are \ConvertApi\Error\Api, which extends
 * \ConvertApi\Error\Base (itself a bare extension of \Exception) - so the
 * status code it carries is exposed via the inherited getCode(), not a
 * bespoke accessor.
 */
class BiolandConvertApiClient {

  /**
   * Viewport width in pixels for the HTML-to-JPG conversion.
   */
  const IMAGE_WIDTH = 1280;

  /**
   * Viewport height in pixels for the HTML-to-JPG conversion.
   */
  const IMAGE_HEIGHT = 800;

  /**
   * Seconds to wait for the page to settle before the screenshot is taken.
   */
  const CONVERSION_DELAY = 2;

  /**
   * Sets the API credentials once per process.
   */
  public function __construct(string $secret) {
    ConvertApi::setApiCredentials($secret);
    ConvertApi::$conversionTimeout = 60;
    ConvertApi::$uploadTimeout = 30;
  }

  /**
   * Builds the parameter array for the HTML->JPG conversion.
   *
   * @see https://www.convertapi.com/html-to-jpg
   */
  public static function buildHtmlToJpgOptions(string $url): array {
    return [
      'Url' => $url,
      'ImageWidth' => self::IMAGE_WIDTH,
      'ImageHeight' => self::IMAGE_HEIGHT,
      'ConversionDelay' => self::CONVERSION_DELAY,
    ];
  }

  /**
   * Builds the parameter array for the JPG->WebP conversion.
   *
   * The JPG is chained by its stored ConvertAPI file URL, never re-uploaded.
   *
   * @see https://www.convertapi.com/jpg-to-webp
   */
  public static function buildJpgToWebpOptions(string $jpgFileUrl): array {
    return ['File' => $jpgFileUrl];
  }

  /**
   * Classifies a \ConvertApi\Error\Api HTTP status.
   *
   * @return string
   *   One of 'transient' (429, 5xx - the worker retries up to its bounded
   *   counter) or 'permanent' (any other 4xx - logged, counter cleared, no
   *   retry). Delegates to the shared table on BiolandUrlScreenshotPolicy.
   */
  public static function classifyExceptionStatus(int $statusCode): string {
    return \Drupal\bioland\BiolandUrlScreenshotPolicy::classifyResponse($statusCode) === 'success'
      ? 'permanent'
      : \Drupal\bioland\BiolandUrlScreenshotPolicy::classifyResponse($statusCode);
  }

  /**
   * Extracts the HTTP status code carried by a ConvertAPI SDK exception.
   *
   * \ConvertApi\Error\Api (extends \ConvertApi\Error\Base extends
   * \Exception) carries no bespoke status accessor - the status is the
   * inherited Exception::getCode(). Anything else (a bug, a network-layer
   * throwable) yields 0, which callers treat as non-classifiable.
   */
  public static function statusFromException(\Throwable $e): int {
    return $e instanceof ConvertApiError ? (int) $e->getCode() : 0;
  }

  /**
   * Converts a URL's first screen to WebP bytes via two chained calls.
   *
   * @return string
   *   The WebP image bytes.
   */
  public function screenshotToWebp(string $url): string {
    $jpg = ConvertApi::convert('jpg', self::buildHtmlToJpgOptions($url), 'html');
    $jpgUrl = $jpg->getFile()->getUrl();

    $webp = ConvertApi::convert('webp', self::buildJpgToWebpOptions($jpgUrl), 'jpg');

    return $webp->getFile()->getContents();
  }

}
