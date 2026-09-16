<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Test stub for Symfony Request.
 */
class Request {

  /**
   * The host.
   *
   * @var string
   */
  protected $host;

  /**
   * Constructs a Request stub.
   *
   * @param string $host
   *   The host this request reports.
   */
  public function __construct($host = '') {
    $this->host = $host;
  }

  /**
   * Gets the host.
   *
   * @return string
   *   The host.
   */
  public function getHost() {
    return $this->host;
  }

}
