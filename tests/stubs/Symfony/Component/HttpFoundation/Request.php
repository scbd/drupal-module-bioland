<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Stub for a Symfony request.
 */
class Request {

  /**
   * The query parameters.
   *
   * @var \Symfony\Component\HttpFoundation\ParameterBag
   */
  public $query;

  /**
   * The request headers.
   *
   * @var \Symfony\Component\HttpFoundation\HeaderBag
   */
  public $headers;

  /**
   * Constructs the request.
   *
   * @param array $query
   *   Query parameters.
   * @param array $headers
   *   Request headers.
   */
  public function __construct(array $query = [], array $headers = []) {
    $this->query = new ParameterBag($query);
    $this->headers = new HeaderBag($headers);
  }

}
