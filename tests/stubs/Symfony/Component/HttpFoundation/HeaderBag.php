<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Stub for a Symfony header bag: case-insensitive on header names.
 */
class HeaderBag extends ParameterBag {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $parameters = []) {
    $normalized = [];
    foreach ($parameters as $name => $value) {
      $normalized[strtolower($name)] = $value;
    }
    parent::__construct($normalized);
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    return parent::has(strtolower($key));
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL) {
    return parent::get(strtolower($key), $default);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    parent::set(strtolower($key), $value);
  }

}
