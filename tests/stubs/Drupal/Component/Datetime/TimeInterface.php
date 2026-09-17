<?php

namespace Drupal\Component\Datetime;

/**
 * Stub interface for the Drupal time service.
 */
interface TimeInterface {

  /**
   * Returns the request time as a UNIX timestamp.
   *
   * @return int
   *   The request timestamp.
   */
  public function getRequestTime();

}
