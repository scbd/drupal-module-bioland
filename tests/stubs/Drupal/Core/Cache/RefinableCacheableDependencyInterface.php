<?php

namespace Drupal\Core\Cache;

/**
 * Stub of core's RefinableCacheableDependencyInterface.
 *
 * Only the method the module calls; core's AccessResult implements it.
 */
interface RefinableCacheableDependencyInterface {

  /**
   * Adds a dependency on an object, merging its cacheability metadata.
   *
   * @param mixed $other_object
   *   The dependency.
   *
   * @return $this
   */
  public function addCacheableDependency($other_object);

}
