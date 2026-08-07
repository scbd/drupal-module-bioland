<?php

namespace Drupal\Core\StringTranslation;

/**
 * Stub trait for StringTranslationTrait.
 *
 * ESCAPING IS PART OF THE CONTRACT, not decoration. Real t() hands its
 * arguments to FormattableMarkup, which runs Html::escape() over every
 * "@"-prefixed placeholder value before substituting it - that escaping is the
 * only thing standing between an attacker-influenced argument and the page. A
 * stub that substituted raw would let an XSS-shaped argument pass every test
 * and still be caught in production, so this one escapes too.
 *
 * The return value is still a plain string rather than a TranslatableMarkup;
 * the module's tests compare translated strings by identity throughout, and
 * nothing here needs the object.
 */
trait StringTranslationTrait {

  /**
   * Translates a string.
   *
   * @param string $string
   *   The string to translate.
   * @param array $args
   *   The replacement arguments.
   * @param array $options
   *   Additional options.
   *
   * @return string
   *   The translated string.
   */
  protected function t($string, array $args = [], array $options = []) {
    foreach ($args as $key => $value) {
      $string = str_replace($key, self::stubPlaceholderValue($key, $value), $string);
    }
    return $string;
  }

  /**
   * Renders one placeholder value the way FormattableMarkup would.
   *
   * @param string $key
   *   The placeholder name, including its type prefix.
   * @param mixed $value
   *   The replacement value.
   *
   * @return string
   *   The value, escaped when its placeholder type calls for it.
   */
  private static function stubPlaceholderValue($key, $value) {
    // "@" is escape-only. The module uses no other placeholder type; "%" and
    // ":" are left verbatim rather than half-implemented, so a future use of
    // one is an obvious gap instead of a subtly wrong one.
    return $key !== '' && $key[0] === '@'
      ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
      : (string) $value;
  }

  /**
   * Formats a plural string.
   *
   * @param int $count
   *   The count.
   * @param string $singular
   *   The singular string.
   * @param string $plural
   *   The plural string.
   * @param array $args
   *   The replacement arguments.
   * @param array $options
   *   Additional options.
   *
   * @return string
   *   The formatted string.
   */
  protected function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
    $args['@count'] = $count;
    return $this->t($count == 1 ? $singular : $plural, $args, $options);
  }

}
