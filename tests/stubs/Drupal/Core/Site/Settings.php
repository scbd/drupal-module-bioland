<?php

namespace Drupal\Core\Site;

/**
 * Stub for Drupal's settings.php reader.
 */
class Settings {

  /**
   * The settings values.
   *
   * @var array
   */
  protected static $values = [];

  /**
   * Returns a setting.
   *
   * @param string $name
   *   The setting name.
   * @param mixed $default
   *   The default when unset.
   *
   * @return mixed
   *   The setting value.
   */
  public static function get($name, $default = NULL) {
    return array_key_exists($name, static::$values) ? static::$values[$name] : $default;
  }

  /**
   * Replaces all settings (test seam).
   *
   * @param array $values
   *   The settings.
   */
  public static function setAll(array $values) {
    static::$values = $values;
  }

}
