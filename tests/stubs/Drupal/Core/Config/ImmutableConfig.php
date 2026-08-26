<?php

namespace Drupal\Core\Config;

/**
 * Stub class for ImmutableConfig.
 */
class ImmutableConfig {

  /**
   * The configuration data.
   *
   * @var array
   */
  protected $data;

  /**
   * The configuration name.
   *
   * @var string
   */
  protected $name;

  /**
   * Constructs a new ImmutableConfig.
   *
   * @param string $name
   *   The configuration name.
   * @param array $data
   *   The configuration data.
   */
  public function __construct(string $name, array $data = []) {
    $this->name = $name;
    $this->data = $data;
  }

  /**
   * Gets a configuration value.
   *
   * @param string $key
   *   The configuration key.
   *
   * @return mixed
   *   The configuration value.
   */
  public function get($key = '') {
    if (empty($key)) {
      return $this->data;
    }

    // Support nested keys using dot notation.
    $keys = explode('.', $key);
    $value = $this->data;
    foreach ($keys as $k) {
      if (is_array($value) && array_key_exists($k, $value)) {
        $value = $value[$k];
      } else {
        return NULL;
      }
    }
    return $value;
  }

  /**
   * Gets the configuration name.
   *
   * @return string
   *   The configuration name.
   */
  public function getName() {
    return $this->name;
  }

}
