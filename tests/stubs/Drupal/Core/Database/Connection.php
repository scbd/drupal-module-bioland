<?php

namespace Drupal\Core\Database;

/**
 * Stub class for Drupal\Core\Database\Connection.
 */
abstract class Connection {

  /**
   * Update query stub.
   */
  public function update($table, array $options = []) {
    return new class {
      public function fields(array $fields) { return $this; }
      public function condition($field, $value = NULL, $operator = NULL) { return $this; }
      public function execute() { return 0; }
    };
  }

}
