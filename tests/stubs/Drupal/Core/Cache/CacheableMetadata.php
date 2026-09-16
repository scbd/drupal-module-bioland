<?php

namespace Drupal\Core\Cache;

/**
 * Stub for CacheableMetadata.
 */
class CacheableMetadata {

  /**
   * Cache tags.
   *
   * @var string[]
   */
  protected $tags = [];

  /**
   * Cache contexts.
   *
   * @var string[]
   */
  protected $contexts = [];

  /**
   * Sets the cache tags.
   *
   * @param string[] $tags
   *   The cache tags.
   *
   * @return $this
   *   This object.
   */
  public function setCacheTags(array $tags) {
    $this->tags = $tags;
    return $this;
  }

  /**
   * Gets the cache tags.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags() {
    return $this->tags;
  }

  /**
   * Sets the cache contexts.
   *
   * @param string[] $contexts
   *   The cache contexts.
   *
   * @return $this
   *   This object.
   */
  public function setCacheContexts(array $contexts) {
    $this->contexts = $contexts;
    return $this;
  }

  /**
   * Gets the cache contexts.
   *
   * @return string[]
   *   The cache contexts.
   */
  public function getCacheContexts() {
    return $this->contexts;
  }

}
