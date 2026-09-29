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
      $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
      $html = is_string($converted) ? $converted : $html;
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
   * Whether an address is publicly routable (mirrors the DMSM guard).
   *
   * FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE cover RFC1918, loopback,
   * link-local and reserved space; CGNAT (100.64.0.0/10) is in neither.
   */
  public static function isPublicIp(string $address): bool {
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === FALSE) {
      return FALSE;
    }
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE) {
      $long = ip2long($address);
      if ($long !== FALSE && ($long & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Resolves a Location header against the URL that returned it.
   */
  public static function resolveRedirect(string $base, string $location): string {
    if (preg_match('#^https?://#i', $location)) {
      return $location;
    }
    $parts = parse_url($base);
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (strpos($location, '//') === 0) {
      return $parts['scheme'] . ':' . $location;
    }
    if (strpos($location, '/') === 0) {
      return $origin . $location;
    }
    $path = $parts['path'] ?? '/';
    return $origin . substr($path, 0, strrpos($path, '/') + 1) . $location;
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
