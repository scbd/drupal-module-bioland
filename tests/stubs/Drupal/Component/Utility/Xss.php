<?php

namespace Drupal\Component\Utility;

/**
 * Stub class for Xss.
 *
 * Mirrors core's behavior closely enough for unit tests: tags not in the
 * allowed list are removed (their text content is kept, as core does), and
 * allowed tags pass through untouched.
 */
class Xss {

  /**
   * The default allowed tags for Xss::filter().
   *
   * @var array
   */
  protected static $htmlTags = [
    'a', 'em', 'strong', 'cite', 'blockquote', 'code', 'ul', 'ol', 'li',
    'dl', 'dt', 'dd',
  ];

  /**
   * The allowed tags for Xss::filterAdmin(), matching core's list.
   *
   * @var array
   */
  protected static $adminTags = [
    'a', 'abbr', 'acronym', 'address', 'article', 'aside', 'b', 'bdi',
    'bdo', 'big', 'blockquote', 'br', 'caption', 'cite', 'code', 'col',
    'colgroup', 'command', 'dd', 'del', 'details', 'dfn', 'div', 'dl',
    'dt', 'em', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4',
    'h5', 'h6', 'header', 'hgroup', 'hr', 'i', 'img', 'ins', 'kbd', 'li',
    'mark', 'menu', 'meter', 'nav', 'ol', 'output', 'p', 'pre', 'progress',
    'q', 'rp', 'rt', 'ruby', 's', 'samp', 'section', 'small', 'span',
    'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot',
    'th', 'thead', 'time', 'tr', 'tt', 'u', 'ul', 'var', 'wbr',
  ];

  /**
   * Filters a string, removing tags not in the allowed list.
   *
   * @param string $string
   *   The string to filter.
   * @param array|null $html_tags
   *   Allowed tag names; defaults to the Xss::filter() default list.
   *
   * @return string
   *   The filtered string.
   */
  public static function filter($string, ?array $html_tags = NULL) {
    $html_tags = $html_tags ?? static::$htmlTags;
    $html_tags = array_map('strtolower', $html_tags);
    return preg_replace_callback(
      '/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/',
      function (array $matches) use ($html_tags) {
        return in_array(strtolower($matches[1]), $html_tags, TRUE) ? $matches[0] : '';
      },
      (string) $string
    );
  }

  /**
   * Filters HTML with the admin-permissive tag list.
   *
   * @param string $string
   *   The string to filter.
   *
   * @return string
   *   The filtered string.
   */
  public static function filterAdmin($string) {
    return static::filter($string, static::$adminTags);
  }

}
