<?php

namespace Drupal\bioland;

/**
 * BL-1191: pure helpers for the Related websites title/description lookup.
 *
 * Kept free of Drupal services so the parsing and the SSRF address check are
 * unit-testable without a site.
 */
class BiolandUrlMetadata {

  /**
   * Longest title handed back to the editor form (node title is 255).
   */
  const TITLE_MAX = 255;

  /**
   * Longest description handed back to the editor form.
   */
  const DESCRIPTION_MAX = 600;

  /**
   * Extracts a page's title and description from its HTML.
   *
   * Open Graph values win over <title> / meta description because sites tune
   * them for link previews, which is exactly this use.
   *
   * @return array{title: string, description: string}
   */
  public static function parse(string $html, string $charset = ''): array {
    $result = ['title' => '', 'description' => ''];
    if (trim($html) === '') {
      return $result;
    }

    $charset = $charset !== '' ? $charset : self::detectCharset($html);
    if ($charset !== '' && strcasecmp($charset, 'utf-8') !== 0 && function_exists('mb_convert_encoding')) {
      try {
        $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
        $html = is_string($converted) ? $converted : $html;
      }
      catch (\ValueError $e) {
        // Unknown charset: parse the bytes as they are.
      }
    }

    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    // The XML prolog makes libxml read the markup as UTF-8.
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $meta = [];
    foreach ($doc->getElementsByTagName('meta') as $tag) {
      $key = strtolower(trim($tag->getAttribute('property') ?: $tag->getAttribute('name')));
      if ($key !== '' && !isset($meta[$key])) {
        $meta[$key] = $tag->getAttribute('content');
      }
    }
    $titleTag = $doc->getElementsByTagName('title')->item(0);

    $result['title'] = self::clean($meta['og:title'] ?? ($titleTag ? $titleTag->textContent : ''), self::TITLE_MAX);
    $result['description'] = self::clean($meta['og:description'] ?? ($meta['description'] ?? ''), self::DESCRIPTION_MAX);
    return $result;
  }

  /**
   * Reads the charset from a Content-Type header value or a <meta> tag.
   */
  public static function detectCharset(string $source): string {
    if (preg_match('/charset=["\']?\s*([a-z0-9_\-:.]+)/i', $source, $m)) {
      return $m[1];
    }
    return '';
  }

  /**
   * IPv4 ranges that are never fetched, whatever filter_var thinks.
   */
  const DENY_V4 = [
    '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
    '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15',
    '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
  ];

  /**
   * IPv6 ranges that are never fetched (after unwrapping embedded IPv4).
   */
  const DENY_V6 = [
    '::ffff:0:0:0/96', '64:ff9b:1::/48', '100::/64', '2001::/32', '2001:db8::/32',
    'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
  ];

  /**
   * Whether an address is publicly routable.
   *
   * Classified from the packed bytes so the answer does not depend on the PHP
   * version's filter_var tables: IPv6 forms that carry an IPv4 address
   * (mapped, compatible, NAT64, 6to4) are judged by that IPv4 address, the
   * deny lists are checked explicitly, and filter_var's private/reserved
   * flags stay on as a second opinion.
   */
  public static function isPublicIp(string $address): bool {
    $packed = @inet_pton($address);
    if ($packed === FALSE) {
      return FALSE;
    }
    if (strlen($packed) === 16) {
      if ($packed === inet_pton('::') || $packed === inet_pton('::1')) {
        return FALSE;
      }
      $embedded = self::embeddedIpv4($packed);
      if ($embedded !== NULL) {
        return self::isPublicIp($embedded);
      }
      $deny = self::DENY_V6;
    }
    else {
      $deny = self::DENY_V4;
    }
    foreach ($deny as $cidr) {
      if (self::inCidr($packed, $cidr)) {
        return FALSE;
      }
    }
    return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== FALSE;
  }

  /**
   * The IPv4 address an IPv6 address carries, if it is a transition form.
   */
  protected static function embeddedIpv4(string $packed): ?string {
    foreach (['::ffff:0:0/96' => 12, '::/96' => 12, '64:ff9b::/96' => 12, '2002::/16' => 2] as $cidr => $offset) {
      if (self::inCidr($packed, $cidr)) {
        return inet_ntop(substr($packed, $offset, 4));
      }
    }
    return NULL;
  }

  /**
   * Whether a packed address falls inside a CIDR range of the same family.
   */
  protected static function inCidr(string $packed, string $cidr): bool {
    [$network, $bits] = explode('/', $cidr);
    $network = inet_pton($network);
    if (strlen($network) !== strlen($packed)) {
      return FALSE;
    }
    $bytes = intdiv((int) $bits, 8);
    if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
      return FALSE;
    }
    $rest = (int) $bits % 8;
    if ($rest === 0) {
      return TRUE;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
  }

  /**
   * Resolves a Location header against the URL that returned it (RFC 3986).
   */
  public static function resolveRedirect(string $base, string $location): string {
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $location)) {
      return $location;
    }
    $parts = parse_url($base);
    if (strpos($location, '//') === 0) {
      return $parts['scheme'] . ':' . $location;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    if ($location === '' || $location[0] === '#') {
      return $origin . $path . $query . $location;
    }
    if ($location[0] === '?') {
      return $origin . $path . $location;
    }
    $split = strcspn($location, '?#');
    $target = substr($location, 0, $split);
    if ($target === '' || $target[0] !== '/') {
      $target = ($path === '' ? '/' : substr($path, 0, strrpos($path, '/') + 1)) . $target;
    }
    return $origin . self::removeDotSegments($target) . substr($location, $split);
  }

  /**
   * Removes "." and ".." segments from an absolute path (RFC 3986 5.2.4).
   */
  protected static function removeDotSegments(string $path): string {
    $segments = explode('/', $path);
    $output = [];
    foreach ($segments as $segment) {
      if ($segment === '..') {
        if (count($output) > 1) {
          array_pop($output);
        }
      }
      elseif ($segment !== '.') {
        $output[] = $segment;
      }
    }
    if (in_array(end($segments), ['.', '..'], TRUE)) {
      $output[] = '';
    }
    return implode('/', $output);
  }

  /**
   * Decodes entities, strips tags, collapses whitespace and truncates.
   */
  protected static function clean(string $text, int $max): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
  }

}
