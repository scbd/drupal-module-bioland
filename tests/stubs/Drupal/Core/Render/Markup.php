<?php

namespace Drupal\Core\Render;

/**
 * Minimal stub of Drupal core's Markup for unit tests.
 */
class Markup {

  /**
   * The markup string.
   *
   * @var string
   */
  protected $string;

  /**
   * Creates a Markup object.
   */
  public static function create($string) {
    $markup = new static();
    $markup->string = (string) $string;
    return $markup;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return $this->string;
  }

}
