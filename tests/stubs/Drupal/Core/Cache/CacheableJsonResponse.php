<?php

namespace Drupal\Core\Cache;

use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Stub for CacheableJsonResponse.
 */
class CacheableJsonResponse {

  /**
   * The response headers.
   *
   * @var \Symfony\Component\HttpFoundation\HeaderBag
   */
  public $headers;

  /**
   * The decoded payload.
   *
   * @var mixed
   */
  protected $data;

  /**
   * Accumulated cacheable metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheability;

  /**
   * Constructs the response.
   *
   * @param mixed $data
   *   The payload.
   */
  public function __construct($data = NULL) {
    $this->data = $data;
    $this->headers = new HeaderBag();
    $this->cacheability = new CacheableMetadata();
  }

  /**
   * Gets the payload as an array.
   *
   * @return mixed
   *   The payload.
   */
  public function getPayload() {
    return $this->data;
  }

  /**
   * Gets the serialized JSON body.
   *
   * @return string
   *   The JSON body.
   */
  public function getContent() {
    return json_encode($this->data);
  }

  /**
   * Adds a cacheable dependency.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $dependency
   *   The dependency.
   *
   * @return $this
   *   This object.
   */
  public function addCacheableDependency($dependency) {
    $this->cacheability->setCacheTags(array_values(array_unique(array_merge($this->cacheability->getCacheTags(), $dependency->getCacheTags()))));
    $this->cacheability->setCacheContexts(array_values(array_unique(array_merge($this->cacheability->getCacheContexts(), $dependency->getCacheContexts()))));
    return $this;
  }

  /**
   * Gets the accumulated cacheable metadata.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The metadata.
   */
  public function getCacheableMetadata() {
    return $this->cacheability;
  }

}
