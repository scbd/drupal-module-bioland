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
   * Constructs the request.
   *
   * @param array $query
   *   Query parameters.
   * @param array $headers
   *   Request headers.
   */
  public function __construct(array $query = [], array $headers = []) {
    $this->query = new InputBag($query);
    $this->headers = new HeaderBag($headers);
  }

}
