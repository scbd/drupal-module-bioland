<?php

/**
 * Stub class for the Drupal global service container.
 */
class Drupal {

  /**
   * The service container.
   *
   * @var array
   */
  protected static $container = [];

  /**
   * Sets a service in the container.
   *
   * @param string $id
   *   The service ID.
   * @param mixed $service
   *   The service.
   */
  public static function setService($id, $service) {
    static::$container[$id] = $service;
  }

  /**
   * Gets a service from the container.
   *
   * @param string $id
   *   The service ID.
   *
   * @return mixed
   *   The service.
   */
  public static function service($id) {
    if (isset(static::$container[$id])) {
      return static::$container[$id];
    }
    
    // Provide default stub for extension.list.module.
    if ($id === 'extension.list.module') {
      return new class {
        public function getPath($name) {
          return '.';
        }
      };
    }
    
    return NULL;
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public static function entityTypeManager() {
    return static::$container['entity_type.manager'] ?? NULL;
  }

  /**
   * Gets the logger factory.
   *
   * @return \Drupal\Core\Logger\LoggerChannelFactoryInterface
   *   The logger factory.
   */
  public static function logger($channel) {
    $factory = static::$container['logger.factory'] ?? NULL;
    return $factory ? $factory->get($channel) : new class {
      public function debug($message, array $context = []) {}
      public function info($message, array $context = []) {}
      public function warning($message, array $context = []) {}
      public function error($message, array $context = []) {}
      public function log($level, $message, array $context = []) {}
    };
  }

  /**
   * Gets the translation service.
   *
   * @return object
   *   The translation service.
   */
  public static function translation() {
    return new class {
      public function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
        $args['@count'] = $count;
        $string = $count == 1 ? $singular : $plural;
        foreach ($args as $key => $value) {
          $string = str_replace($key, $value, $string);
        }
        return $string;
      }
    };
  }

  /**
   * Gets the messenger service.
   *
   * @return object
   *   The messenger.
   */
  public static function messenger() {
    return new class {
      public function addMessage($message, $type = 'status') {}
      public function addStatus($message) {}
      public function addWarning($message) {}
      public function addError($message) {}
    };
  }

  /**
   * Resets the container.
   */
  public static function resetContainer() {
    static::$container = [];
  }

}
