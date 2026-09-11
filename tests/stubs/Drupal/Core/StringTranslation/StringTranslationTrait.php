<?php

namespace Drupal\Core\StringTranslation;

/**
 * Stub trait for StringTranslationTrait.
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
    // Simple stub: just replace placeholders.
    foreach ($args as $key => $value) {
      $string = str_replace($key, $value, $string);
    }
    return $string;
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
