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
   * Throws for an unregistered id, exactly as the real container does. It used
   * to return NULL instead, which quietly unpinned every \Drupal::hasService()
   * guard in the module: production code that skipped the guard would fatal on
   * a real site but stayed green here, because the missing service arrived as
   * a harmless NULL that the caller's `instanceof` check absorbed. Callers
   * that mean to degrade must ask hasService() first; callers that do not are
   * now supposed to blow up in the suite.
   *
   * @param string $id
   *   The service ID.
   *
   * @return mixed
   *   The service.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   *   When the container has no such service.
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

    throw new \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException($id);
  }

  /**
   * Whether a service is registered.
   *
   * Mirrors \Drupal::hasService(). The real container THROWS
   * ServiceNotFoundException from service() for an unregistered id rather
   * than returning NULL, so callers that mean to degrade instead of fatal
   * must ask this first.
   *
   * @param string $id
   *   The service ID.
   *
   * @return bool
   *   TRUE when the container has the service.
   */
  public static function hasService($id) {
    return isset(static::$container[$id]) || $id === 'extension.list.module';
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

  /**
   * Gets the state service.
   *
   * @return object
   *   The state service.
   */
  public static function state() {
    return static::$container['state'] ?? new class {
      protected $values = [];

      public function get($key, $default = NULL) {
        return $this->values[$key] ?? $default;
      }

      public function set($key, $value) {
        $this->values[$key] = $value;
      }

      public function delete($key) {
        unset($this->values[$key]);
      }
    };
  }

}
