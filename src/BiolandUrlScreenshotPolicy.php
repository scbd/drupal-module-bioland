<?php

namespace Drupal\bioland;

/**
 * Pure decision logic for BL-1191 (website screenshot via ConvertAPI).
 *
 * Kept dependency-free (no Drupal services) so it is testable without a
 * container, mirroring the style of BiolandAdditionalTagDefaults.
 */
class BiolandUrlScreenshotPolicy {

  /**
   * Taxonomy term id for the "Related websites" content-type placement.
   */
  const RELATED_WEBSITES_TID = 13;

  /**
   * Settings key name for the API secret's Settings::get() fallback.
   */
  const SECRET_SETTINGS_KEY = 'bioland_convert_api_secret';

  /**
   * Environment variable name carrying the ConvertAPI secret.
   */
  const SECRET_ENV_VAR = 'CONVERT_API_SECRET';

  /**
   * Whether an entity qualifies for a website screenshot.
   */
  public static function qualifies(
    string $bundle,
    ?int $tid,
    ?string $url,
    bool $enabled,
    bool $hasSecret
  ): bool {
    if (!$enabled || !$hasSecret) {
      return FALSE;
    }
    if ($bundle !== 'content') {
      return FALSE;
    }
    if ($tid !== self::RELATED_WEBSITES_TID) {
      return FALSE;
    }
    if ($url === NULL || $url === '') {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Whether the URL changed between the original and the new revision.
   */
  public static function urlChanged(?string $originalUri, ?string $newUri): bool {
    if ($originalUri === NULL) {
      return TRUE;
    }
    return $originalUri !== $newUri;
  }

  /**
   * Validates a URL is safe to hand to an external screenshot service.
   *
   * Mirrors the three checks BiolandDmsmConfigService::assertUrlIsFetchable()
   * performs (scheme, embedded credentials, host presence), duplicated here
   * rather than extracted because the two services differ in every other
   * respect and extraction would cost more LOC than it saves.
   */
  public static function isFetchableUrl(?string $url): bool {
    if ($url === NULL || $url === '') {
      return FALSE;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
      return FALSE;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
      return FALSE;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], TRUE)) {
      return FALSE;
    }
    $host = (string) ($parts['host'] ?? '');
    return $host !== '';
  }

  /**
   * Resolves the ConvertAPI secret: env var first, Settings:: fallback.
   *
   * @param callable $settingsGetter
   *   A callable(string $key): mixed, standing in for
   *   \Drupal\Core\Site\Settings::get() so this stays container-free.
   */
  public static function resolveSecret(callable $settingsGetter): string {
    $env = getenv(self::SECRET_ENV_VAR);
    if (is_string($env) && $env !== '') {
      return $env;
    }
    $fromSettings = $settingsGetter(self::SECRET_SETTINGS_KEY);
    return is_string($fromSettings) ? $fromSettings : '';
  }

  /**
   * Builds the media "name" from the node title.
   */
  public static function buildMediaName(string $title, string $date): string {
    return sprintf('%s - website screenshot %s', self::cleanText($title, 200), $date);
  }

  /**
   * Builds the media "alt" text from the node title and the URL host.
   */
  public static function buildMediaAlt(string $title, string $host): string {
    return sprintf('Screenshot of the %s website (%s)', self::cleanText($title, 180), $host);
  }

  /**
   * Builds the media "title" from a description/summary, or the host.
   */
  public static function buildMediaTitle(?string $descriptionSummary, string $host): string {
    $clean = self::cleanText((string) $descriptionSummary, 150);
    return $clean !== '' ? $clean : $host;
  }

  /**
   * Strips tags, trims, and truncates text to a maximum length.
   */
  public static function cleanText(string $text, int $maxLength): string {
    $clean = trim(strip_tags($text));
    if (strlen($clean) <= $maxLength) {
      return $clean;
    }
    return rtrim(substr($clean, 0, $maxLength));
  }

  /**
   * Classifies a ConvertAPI HTTP status as success, transient, or permanent.
   *
   * @return string
   *   One of 'success', 'transient', 'permanent'.
   */
  public static function classifyResponse(int $statusCode): string {
    if ($statusCode >= 200 && $statusCode < 300) {
      return 'success';
    }
    if ($statusCode === 429 || $statusCode >= 500) {
      return 'transient';
    }
    return 'permanent';
  }

}
