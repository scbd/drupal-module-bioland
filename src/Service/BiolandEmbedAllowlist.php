<?php

namespace Drupal\bioland\Service;

/**
 * Normalises and validates bioland.settings embed.allowed_origins (BL-1218).
 *
 * The head frames an iframe only when its URL has exactly an entry's scheme
 * and host (port included) and its path extends the entry's path on a
 * segment boundary. Entries are therefore stored in one canonical shape:
 * lowercase scheme and host, no default port, no trailing slash.
 */
final class BiolandEmbedAllowlist {

  /**
   * The iframe sandbox tokens the head accepts (bioland-head app/utils/html.js).
   */
  public const SANDBOX_TOKENS = [
    'allow-downloads', 'allow-forms', 'allow-modals', 'allow-orientation-lock',
    'allow-pointer-lock', 'allow-popups', 'allow-popups-to-escape-sandbox',
    'allow-presentation', 'allow-same-origin', 'allow-scripts',
    'allow-storage-access-by-user-activation', 'allow-top-navigation',
    'allow-top-navigation-by-user-activation',
    'allow-top-navigation-to-custom-protocols',
  ];

  /**
   * Tokens the head always drops, so saving one would be dead config.
   */
  public const NEVER_ALLOWED_TOKENS = [
    'allow-top-navigation', 'allow-popups-to-escape-sandbox',
    'allow-top-navigation-to-custom-protocols',
  ];

  /**
   * Most rows the settings form keeps.
   */
  public const MAX_ENTRIES = 50;

  /**
   * Entries every site ships with. The site's own files URL is site-specific.
   */
  public const DEFAULTS = [
    ['url' => 'https://www.youtube.com', 'label' => 'YouTube', 'sandbox' => ''],
    ['url' => 'https://youtube.com', 'label' => 'YouTube', 'sandbox' => ''],
    ['url' => 'https://www.youtube-nocookie.com', 'label' => 'YouTube (privacy-enhanced)', 'sandbox' => ''],
    ['url' => 'https://player.vimeo.com', 'label' => 'Vimeo', 'sandbox' => ''],
    ['url' => 'https://portal.geobon.org', 'label' => 'GEO BON', 'sandbox' => ''],
    ['url' => 'https://bch.cbd.int', 'label' => 'Biosafety Clearing-House', 'sandbox' => ''],
    ['url' => 'https://app.powerbi.com/view', 'label' => 'Power BI', 'sandbox' => ''],
  ];

  /**
   * Returns the canonical form of an allowlist URL, or NULL when invalid.
   *
   * Accepts only absolute http(s) URLs with a host and no userinfo, query or
   * fragment. The character check runs on the raw string first so parse_url()
   * never sees input that a browser would parse differently (backslashes,
   * whitespace, an '@' in the authority).
   */
  public static function normalizeUrl(string $url): ?string {
    if (!preg_match('~^https?://[^/?#@\\\\\s]+(/[^?#@\\\\\s]*)?$~i', trim($url))) {
      return NULL;
    }
    $parts = parse_url(trim($url));
    $host = strtolower($parts['host'] ?? '');
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host)
      || (self::isNumericHost($host) && !self::isDottedQuad($host))) {
      return NULL;
    }
    $scheme = strtolower($parts['scheme']);
    $port = $parts['port'] ?? NULL;
    if ($port !== NULL && ($port < 1 || $port > 65535)) {
      return NULL;
    }
    if ($port === ($scheme === 'https' ? 443 : 80)) {
      $port = NULL;
    }
    $path = rtrim($parts['path'] ?? '', '/');
    // Encoded slashes and dots: the head refuses the first and decodes the
    // second, so either would make the stored prefix mean something else.
    if (preg_match('~(^|/)(\.|%2e){1,2}(/|$)|%2f|%5c~i', $path)) {
      return NULL;
    }
    return $scheme . '://' . $host . ($port !== NULL ? ':' . $port : '') . $path;
  }

  /**
   * Whether WHATWG would parse the host as IPv4 (last label numeric or hex).
   */
  private static function isNumericHost(string $host): bool {
    return (bool) preg_match('/(^|\.)(\d+|0x[0-9a-f]*)$/', $host);
  }

  /**
   * Whether the host is canonical dotted-quad IPv4, as WHATWG serialises it.
   */
  public static function isDottedQuad(string $host): bool {
    return (bool) preg_match('/^((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)$/', $host);
  }

  /**
   * Splits a sandbox string into lowercase, de-duplicated tokens.
   *
   * @return string[]
   *   The tokens in first-seen order.
   */
  public static function sandboxTokens(string $sandbox): array {
    $tokens = preg_split('/\s+/', strtolower(trim($sandbox)), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique($tokens));
  }

  /**
   * Validates one entry.
   *
   * @return string[]
   *   Error codes: 'url', 'sandbox_token', 'sandbox_never' or
   *   'sandbox_escape'. Empty = valid.
   */
  public static function validateEntry(array $entry): array {
    $errors = [];
    if (self::normalizeUrl((string) ($entry['url'] ?? '')) === NULL) {
      $errors[] = 'url';
    }
    $tokens = self::sandboxTokens((string) ($entry['sandbox'] ?? ''));
    if (array_diff($tokens, self::SANDBOX_TOKENS)) {
      $errors[] = 'sandbox_token';
    }
    if (array_intersect($tokens, self::NEVER_ALLOWED_TOKENS)) {
      $errors[] = 'sandbox_never';
    }
    // Together these let the framed page remove its own sandbox attribute.
    if (!array_diff(['allow-scripts', 'allow-same-origin'], $tokens)) {
      $errors[] = 'sandbox_escape';
    }
    return $errors;
  }

  /**
   * Whether a URL may be framed under the given allowlist entries.
   *
   * The URL's scheme, host and port must equal an entry's exactly, and its
   * path must equal the entry's path or extend it past a '/'. Query and
   * fragment are ignored. Userinfo, encoded '/' or '\', dot segments
   * (encoded or not) and whitespace reject the URL outright.
   */
  public static function matches(string $url, array $entries): bool {
    if (preg_match('/[\x00-\x20\x7f]/', $url) || !preg_match('~^(https?://[^/?#]*)([^?#]*)~i', $url, $m)
      || preg_match('~%(2f|5c)|(^|/)(\.|%2e){1,2}(/|$)~i', $m[2])) {
      return FALSE;
    }
    $candidate = self::normalizeUrl($m[1] . $m[2]);
    if ($candidate === NULL) {
      return FALSE;
    }
    [$origin, $path] = self::splitOrigin($candidate);
    foreach ($entries as $entry) {
      $allowed = is_array($entry) ? self::normalizeUrl((string) ($entry['url'] ?? '')) : NULL;
      if ($allowed === NULL) {
        continue;
      }
      [$allowed_origin, $allowed_path] = self::splitOrigin($allowed);
      if ($origin === $allowed_origin && ($allowed_path === '' || $path === $allowed_path || str_starts_with($path, $allowed_path . '/'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Splits a normalizeUrl() result into its origin and path.
   *
   * @return string[]
   *   The origin (scheme://host[:port]) and the path ('' for none).
   */
  private static function splitOrigin(string $url): array {
    $slash = strpos($url, '/', strpos($url, '://') + 3);
    return $slash === FALSE ? [$url, ''] : [substr($url, 0, $slash), substr($url, $slash)];
  }

  /**
   * Returns an entry in its stored shape. Call only after validateEntry().
   */
  public static function normalizeEntry(array $entry): array {
    return [
      'url' => (string) self::normalizeUrl((string) ($entry['url'] ?? '')),
      'label' => trim((string) ($entry['label'] ?? '')),
      'sandbox' => implode(' ', self::sandboxTokens((string) ($entry['sandbox'] ?? ''))),
    ];
  }

}
