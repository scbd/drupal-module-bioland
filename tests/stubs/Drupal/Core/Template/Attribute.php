<?php

namespace Drupal\Core\Template;

/**
 * Stub for Drupal's Attribute: holds values and returns them from toArray().
 */
class Attribute {

  /**
   * The attribute values.
   *
   * @var array
   */
  protected array $storage;

  /**
   * Constructs an Attribute object.
   */
  public function __construct(array $attributes = []) {
    $this->storage = $attributes;
  }

  /**
   * Returns the attributes as an array.
   */
  public function toArray(): array {
    return $this->storage;
  }

}
