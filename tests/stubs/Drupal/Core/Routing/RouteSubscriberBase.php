<?php

namespace Drupal\Core\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * Minimal stub of core's RouteSubscriberBase for standalone unit tests.
 */
abstract class RouteSubscriberBase {

  /**
   * Alters existing routes for a specific collection.
   */
  abstract protected function alterRoutes(RouteCollection $collection);

  /**
   * Delegates to alterRoutes(), standing in for the route alter event.
   */
  public function onAlterRoutes(RouteCollection $collection): void {
    $this->alterRoutes($collection);
  }

}
