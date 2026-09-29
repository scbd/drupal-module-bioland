<?php

namespace Drupal\Core\Field;

/**
 * Minimal stub of the core field item list.
 */
class FieldItemList {

  /**
   * The field items.
   *
   * @var array
   */
  protected $list = [];

  public function __construct(protected $entity) {}

  public function getEntity() {
    return $this->entity;
  }

  protected function createItem($offset = 0, $value = NULL) {
    return (object) ['value' => $value];
  }

}
