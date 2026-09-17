<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Stub for a Symfony request.
 *
 * $query is an InputBag, matching Symfony 6+: get() rejects a non-scalar
 * stored value with BadRequestException (how "?parent[]=x" becomes a 400
 * before any controller is reached), and has() is a plain presence check,
 * which BiolandConfigApiAccessCheck relies on to reject a query key
 * regardless of its value.
 *
 * The host is carried too, because BiolandDmsmConfigService resolves the
 * hostname it operates on from the current request.
 */
class Request {

  /**
   * The query parameters.
   *
   * @var \Symfony\Component\HttpFoundation\InputBag
   */
  public $query;

  /**
   * The request headers.
   *
   * @var \Symfony\Component\HttpFoundation\HeaderBag
   */
  public $headers;

  /**
   * The host.
   *
   * @var string
   */
  protected $host;

  /**
   * Constructs the request.
   *
   * @param array $query
   *   Query parameters.
   * @param array $headers
   *   Request headers.
   * @param string $host
   *   The host this request reports.
   */
  public function __construct(array $query = [], array $headers = [], $host = '') {
    $this->query = new InputBag($query);
    $this->headers = new HeaderBag($headers);
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
