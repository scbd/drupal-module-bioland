<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Test stub for Symfony Request.
 *
 * Only the public $query bag, which is the whole request surface the module
 * touches (BiolandMenuController reads ?parent= and nothing else).
 */
class Request {

  /**
   * The query string parameters.
   *
   * @var \Symfony\Component\HttpFoundation\InputBag
   */
  public $query;

  /**
   * Constructs the request.
   *
   * @param array $query
   *   The query string parameters.
   */
  public function __construct(array $query = []) {
    $this->query = new InputBag($query);
  }

}
