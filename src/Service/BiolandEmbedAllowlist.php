<?php

namespace Drupal\bioland\Service;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

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
   * Request attribute set when an embed save added to the list (BL-1289).
   */
  public const CHANGED_ATTRIBUTE = '_bioland_embed_allowlist_changed';

  /**
   * Query parameter that makes the head clear its site cache (BL-1289).
   *
   * The head clears it for a logged-in cache-admin session only, once per
   * distinct value, so every link needs a fresh nonce.
   */
  public const HEAD_CACHE_CLEAR_PARAM = 'seachain-taisce';

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
   * classify(): the URL matches an entry.
   */
  public const MATCH = 'match';

  /**
   * classify(): no entry has the URL's scheme, host and port.
   */
  public const HOST_NOT_ALLOWED = 'host';

  /**
   * classify(): the origin is allowed, but the path is not or is malformed.
   */
  public const PATH_NOT_ALLOWED = 'path';

  /**
   * Whether a URL may be framed under the given allowlist entries.
   */
  public static function matches(string $url, array $entries): bool {
    return self::findEntry($url, $entries) !== NULL;
  }

  /**
   * Returns the first entry the URL matches, or NULL.
   */
  public static function findEntry(string $url, array $entries): ?array {
    return self::scan($url, $entries)[1];
  }

  /**
   * Returns MATCH, HOST_NOT_ALLOWED or PATH_NOT_ALLOWED for a URL.
   */
  public static function classify(string $url, array $entries): string {
    return self::scan($url, $entries)[0];
  }

  /**
   * Matches a URL against the entries.
   *
   * The URL's scheme, host and port must equal an entry's exactly (userinfo
   * is refused), and its path must equal the entry's path or extend it past
   * a '/'. Query and fragment are ignored. Encoded '/' or '\', dot segments
   * (encoded or not), backslashes and whitespace fail the path.
   *
   * @return array
   *   The classify() status and the matched entry (NULL unless MATCH).
   */
  private static function scan(string $url, array $entries): array {
    $origin = preg_match('~^(https?://[^/?#]*)([^?#]*)~i', $url, $m) ? self::normalizeUrl($m[1]) : NULL;
    $paths = [];
    foreach ($origin === NULL ? [] : $entries as $entry) {
      $allowed = is_array($entry) ? self::normalizeUrl((string) ($entry['url'] ?? '')) : NULL;
      if ($allowed !== NULL && ($allowed === $origin || str_starts_with($allowed, $origin . '/'))) {
        $paths[] = [substr($allowed, strlen($origin)), $entry];
      }
    }
    if (!$paths) {
      return [self::HOST_NOT_ALLOWED, NULL];
    }
    if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('~%(2f|5c)|(^|/)(\.|%2e){1,2}(/|$)~i', $m[2])) {
      return [self::PATH_NOT_ALLOWED, NULL];
    }
    $path = rtrim($m[2], '/');
    foreach ($paths as [$allowed_path, $entry]) {
      if ($allowed_path === '' || $path === $allowed_path || str_starts_with($path, $allowed_path . '/')) {
        return [self::MATCH, $entry];
      }
    }
    return [self::PATH_NOT_ALLOWED, NULL];
  }

  /**
   * The normalised URLs of the valid entries, de-duplicated.
   *
   * @return string[]
   *   Canonical entry URLs in list order.
   */
  public static function entryUrls(array $entries): array {
    $urls = [];
    foreach ($entries as $entry) {
      $url = is_array($entry) ? self::normalizeUrl((string) ($entry['url'] ?? '')) : NULL;
      if ($url !== NULL) {
        $urls[$url] = $url;
      }
    }
    return array_values($urls);
  }

  /**
   * The sandbox attribute the head applies for an entry (html.js toSandbox).
   *
   * Unknown and never-allowed tokens are dropped, as is allow-same-origin
   * beside allow-scripts. NULL when the entry sets no sandbox; '' when every
   * token was filtered out, so a typo never fails open.
   */
  public static function frameSandbox(array $entry): ?string {
    $tokens = self::sandboxTokens((string) ($entry['sandbox'] ?? ''));
    if (!$tokens) {
      return NULL;
    }
    $tokens = array_diff(array_intersect($tokens, self::SANDBOX_TOKENS), self::NEVER_ALLOWED_TOKENS);
    if (in_array('allow-scripts', $tokens, TRUE)) {
      $tokens = array_diff($tokens, ['allow-same-origin']);
    }
    return implode(' ', $tokens);
  }

  /**
   * The entry that would admit a URL, or NULL.
   *
   * For users who add hosts by saving an embed: https only, on a dotted host
   * that is not an IP literal. Scoped to the URL's first path segment (for
   * example https://claude.ai/artifact), so one save never opens a whole
   * shared host. No sandbox, like the shipped defaults; a reviewer can add
   * one on the settings page.
   */
  public static function autoEntry(string $url): ?array {
    if (!preg_match('~^(https://[^/?#]*)(/[^/?#]*)?~i', trim($url), $m)) {
      return NULL;
    }
    $origin = self::normalizeUrl($m[1]);
    $host = (string) parse_url((string) $origin, PHP_URL_HOST);
    if ($origin === NULL || !str_contains($host, '.') || self::isDottedQuad($host)) {
      return NULL;
    }
    $entry_url = self::normalizeUrl($origin . ($m[2] ?? ''));
    if ($entry_url === NULL) {
      return NULL;
    }
    return ['url' => $entry_url, 'label' => substr($entry_url, strlen('https://')), 'sandbox' => ''];
  }

  /**
   * The entries plus an auto entry admitting the URL, or NULL.
   *
   * NULL when the URL already matches, the list is full, or the URL would
   * still not match (a malformed path). A bare-origin entry is never added
   * for a host already listed under a narrower path.
   */
  public static function withAutoEntry(string $url, array $entries): ?array {
    $url = trim($url);
    $entry = self::autoEntry($url);
    $status = self::classify($url, $entries);
    if ($entry === NULL || count($entries) >= self::MAX_ENTRIES || $status === self::MATCH
      || ($status === self::PATH_NOT_ALLOWED && parse_url($entry['url'], PHP_URL_PATH) === NULL)) {
      return NULL;
    }
    $entries[] = $entry;
    return self::classify($url, $entries) === self::MATCH ? $entries : NULL;
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

  /**
   * Returns a URL query that makes the head reload the list (BL-1289).
   *
   * The nonce is 16 hex characters, well inside the head's nonce regex
   * /^[A-Za-z0-9_-]{1,64}$/ in bioland-head server/middleware/00.cache-clear.ts.
   */
  public static function headCacheClearQuery(): array {
    return [self::HEAD_CACHE_CLEAR_PARAM => bin2hex(random_bytes(8))];
  }

  /**
   * Returns a status message linking to the front page with a fresh nonce.
   *
   * For saves that change the list but do not send the editor to the head.
   */
  public static function headCacheClearMessage(): TranslatableMarkup {
    $url = Url::fromRoute('<front>', [], ['query' => self::headCacheClearQuery()]);
    return new TranslatableMarkup('The front end still uses the old embed allowlist for a few minutes. <a href=":url">Clear the front end cache</a> to use the new one now.', [':url' => $url->toString()]);
  }

}
