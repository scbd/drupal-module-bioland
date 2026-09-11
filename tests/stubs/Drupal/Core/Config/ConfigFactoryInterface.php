<?php

namespace Drupal\Core\Config;

/**
 * Stub interface for ConfigFactoryInterface.
 */
interface ConfigFactoryInterface {

  /**
   * Returns an immutable configuration object.
   *
   * @param string $name
   *   The name of the configuration object to return.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   A configuration object.
   */
  public function get($name);

  /**
   * Returns a mutable configuration object.
   *
   * @param string $name
   *   The name of the configuration object to return.
   *
   * @return \Drupal\Core\Config\Config
   *   A configuration object.
   */
  public function getEditable($name);

}
