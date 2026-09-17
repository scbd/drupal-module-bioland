<?php

namespace Drupal\Core\State;

/**
 * Stub interface for StateInterface.
 */
interface StateInterface {

  /**
   * Returns a stored value.
   *
   * @param string $key
   *   The key.
   * @param mixed $default
   *   The default when the key is absent.
   *
   * @return mixed
   *   The value.
   */
  public function get($key, $default = NULL);

  /**
   * Stores a value.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function set($key, $value);

  /**
   * Deletes a stored value.
   *
   * @param string $key
   *   The key.
   */
  public function delete($key);

}
