<?php

namespace Drupal\Core\Config;

/**
 * Stub mutable Config used by unit tests.
 *
 * Extends the immutable stub with set()/clear()/save()/delete() and records
 * whether it was persisted, so tests can assert the exact config writes a form
 * submit performs.
 */
class Config extends ImmutableConfig {

  /**
   * Whether save() has been called.
   *
   * @var bool
   */
  public $saved = FALSE;

  /**
   * Whether delete() has been called.
   *
   * @var bool
   */
  public $deleted = FALSE;

  /**
   * Sets a value using dot-notation nested keys.
   *
   * @param string $key
   *   The configuration key.
   * @param mixed $value
   *   The value to set.
   *
   * @return $this
   */
  public function set($key, $value) {
    $keys = explode('.', $key);
    $ref = &$this->data;
    foreach ($keys as $i => $k) {
      if ($i === count($keys) - 1) {
        $ref[$k] = $value;
      }
      else {
        if (!isset($ref[$k]) || !is_array($ref[$k])) {
          $ref[$k] = [];
        }
        $ref = &$ref[$k];
      }
    }
    return $this;
  }

  /**
   * Removes a value using dot-notation nested keys.
   *
   * @param string $key
   *   The configuration key.
   *
   * @return $this
   */
  public function clear($key) {
    $keys = explode('.', $key);
    $ref = &$this->data;
    foreach ($keys as $i => $k) {
      if ($i === count($keys) - 1) {
        unset($ref[$k]);
      }
      elseif (isset($ref[$k]) && is_array($ref[$k])) {
        $ref = &$ref[$k];
      }
      else {
        break;
      }
    }
    return $this;
  }

  /**
   * Records that the config was persisted.
   *
   * @return $this
   */
  public function save($has_trusted_data = FALSE) {
    $this->saved = TRUE;
    return $this;
  }

  /**
   * Records that the config was deleted and clears its data.
   *
   * @return $this
   */
  public function delete() {
    $this->deleted = TRUE;
    $this->data = [];
    return $this;
  }

}
