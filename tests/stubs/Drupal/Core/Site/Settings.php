<?php

namespace Drupal\Core\Site;

/**
 * Stub for the Drupal settings service.
 */
class Settings {

  /**
   * The settings storage.
   *
   * @var array
   */
  protected $storage;

  /**
   * Constructs the settings object.
   *
   * @param array $settings
   *   The settings.
   */
  public function __construct(array $settings = []) {
    $this->storage = $settings;
  }

  /**
   * Gets a setting.
   *
   * @param string $name
   *   The setting name.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The value.
   */
  public function get($name, $default = NULL) {
    return array_key_exists($name, $this->storage) ? $this->storage[$name] : $default;
  }

}
