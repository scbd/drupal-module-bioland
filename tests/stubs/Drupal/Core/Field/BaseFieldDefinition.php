<?php

namespace Drupal\Core\Field;

/**
 * Minimal stub of the core base field definition (fluent setters only).
 */
class BaseFieldDefinition {

  /**
   * Values recorded by the fluent setters, keyed by setter name.
   *
   * @var array
   */
  public $values = [];

  public function __construct(public string $type) {}

  public static function create($type) {
    return new static($type);
  }

  public function __call($name, array $args) {
    $this->values[$name] = $args[0] ?? NULL;
    return $this;
  }

}
