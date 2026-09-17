<?php

namespace Drupal\Core\Site;

/**
 * Stub for Drupal's settings.php reader.
 *
 * Mirrors the real class closely enough for both consumers: a static
 * Settings::get() for procedural code (the update hooks in
 * includes/bioland.install.dmsm.inc), and an injectable instance for
 * BiolandConfigApiAccessCheck, which receives '@settings' and calls get() on
 * it. As in Drupal core, constructing an instance makes it the singleton the
 * static accessor reads.
 */
class Settings {

  /**
   * The active instance.
   *
   * @var static|null
   */
  protected static $instance;

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
    static::$instance = $this;
  }

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
    $storage = static::$instance === NULL ? [] : static::$instance->storage;

    return array_key_exists($name, $storage) ? $storage[$name] : $default;
  }

  /**
   * Replaces all settings (test seam).
   *
   * @param array $values
   *   The settings.
   */
  public static function setAll(array $values) {
    new static($values);
  }

}
