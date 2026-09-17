<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Stub for a Symfony parameter bag.
 */
class ParameterBag {

  /**
   * The parameters.
   *
   * @var array
   */
  protected $parameters;

  /**
   * Constructs the bag.
   *
   * @param array $parameters
   *   The parameters.
   */
  public function __construct(array $parameters = []) {
    $this->parameters = $parameters;
  }

  /**
   * Whether a key is present.
   *
   * @param string $key
   *   The key.
   *
   * @return bool
   *   TRUE when present.
   */
  public function has($key) {
    return array_key_exists($key, $this->parameters);
  }

  /**
   * Gets a value.
   *
   * @param string $key
   *   The key.
   * @param mixed $default
   *   The default.
   *
   * @return mixed
   *   The value.
   */
  public function get($key, $default = NULL) {
    return $this->parameters[$key] ?? $default;
  }

  /**
   * Sets a value.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function set($key, $value) {
    $this->parameters[$key] = $value;
  }

  /**
   * Returns all parameters.
   *
   * @return array
   *   The parameters.
   */
  public function all() {
    return $this->parameters;
  }

}
