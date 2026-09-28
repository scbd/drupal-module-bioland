<?php

namespace Drupal\Core\StringTranslation;

/**
 * Stub class for TranslatableMarkup.
 *
 * Mimics the interface of Drupal\Core\StringTranslation\TranslatableMarkup
 * for unit tests where a TranslatableMarkup object is created but only its
 * string cast is compared. Escaping (via FormattableMarkup) is the same as
 * in the StringTranslationTrait stub.
 */
class TranslatableMarkup {

  /**
   * The original string before translation.
   *
   * @var string
   */
  protected $string;

  /**
   * The replacement arguments.
   *
   * @var array
   */
  protected $args;

  /**
   * The rendered string.
   *
   * @var string|null
   */
  protected $rendered;

  /**
   * Constructs a TranslatableMarkup object.
   *
   * @param string $string
   *   The string to translate.
   * @param array $args
   *   The replacement arguments.
   * @param array $options
   *   Additional options.
   */
  public function __construct($string, array $args = [], array $options = []) {
    $this->string = $string;
    $this->args = $args;
  }

  /**
   * Returns the string representation of the object.
   *
   * @return string
   *   The rendered string.
   */
  public function __toString(): string {
    if ($this->rendered === null) {
      $string = $this->string;
      foreach ($this->args as $key => $value) {
        $string = str_replace($key, $this->renderPlaceholder($key, $value), $string);
      }
      $this->rendered = $string;
    }
    return $this->rendered;
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
  private static function renderPlaceholder($key, $value) {
    return $key !== '' && $key[0] === '@'
      ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
      : (string) $value;
  }

}
