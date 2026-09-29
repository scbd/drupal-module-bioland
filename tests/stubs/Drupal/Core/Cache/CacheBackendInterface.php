<?php

namespace Drupal\Core\Cache;

/**
 * Test stub for the cache backend contract.
 */
interface CacheBackendInterface {

  /**
   * Returns a cache item object (with a data property) or FALSE.
   */
  public function get($cid, $allow_invalid = FALSE);

  /**
   * Stores data until the expire timestamp.
   */
  public function set($cid, $data, $expire = -1, array $tags = []);

}
