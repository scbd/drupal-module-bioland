<?php

namespace Drupal\Core\Flood;

/**
 * Stub for core's flood control service.
 */
interface FloodInterface {

  public function register($name, $window = 3600, $identifier = NULL);

  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL);

}
