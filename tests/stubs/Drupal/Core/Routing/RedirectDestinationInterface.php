<?php

namespace Drupal\Core\Routing;

/**
 * Stub interface for the redirect destination service.
 *
 * Only getAsArray(), the form the module uses when building a link query.
 */
interface RedirectDestinationInterface {

  /**
   * Returns the destination as a query array.
   *
   * @return array
   *   A ['destination' => <path>] array.
   */
  public function getAsArray();

}
