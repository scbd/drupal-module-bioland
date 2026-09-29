<?php

namespace Drupal\Core;

/**
 * Stub for Drupal\Core\DestructableInterface.
 */
interface DestructableInterface {

  /**
   * Performs destruct operations after the response was sent.
   */
  public function destruct();

}
