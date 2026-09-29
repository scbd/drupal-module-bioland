<?php

namespace Drupal\Core\Entity;

/**
 * Minimal stub of the core fieldable entity interface.
 */
interface FieldableEntityInterface {

  public function bundle();

  public function hasField($field_name);

  public function get($field_name);

}
