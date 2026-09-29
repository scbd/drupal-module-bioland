<?php

namespace Drupal\media\Entity;

/**
 * Stub for Drupal\media\Entity\Media (only the members this module touches).
 */
class Media {

  protected static $nextId = 1;

  protected $values;

  protected $mid;

  public static function create(array $values) {
    $instance = new static();
    $instance->values = $values;
    return $instance;
  }

  /**
   * Saves the media entity (no-op stub; assigns an incrementing ID).
   */
  public function save() {
    $this->mid = static::$nextId++;
  }

  /**
   * Gets the media entity ID.
   *
   * @return int
   */
  public function id() {
    return $this->mid;
  }

  /**
   * Gets the values this media was created with (test introspection).
   *
   * @return array
   */
  public function getCreateValues() {
    return $this->values;
  }

}
