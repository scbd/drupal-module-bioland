<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Test stub for Symfony RequestStack.
 */
class RequestStack {

  /**
   * The pushed requests.
   *
   * @var \Symfony\Component\HttpFoundation\Request[]
   */
  protected $requests = [];

  /**
   * Gets the current request.
   *
   * @return \Symfony\Component\HttpFoundation\Request|null
   *   The current request or NULL.
   */
  public function getCurrentRequest() {
    return end($this->requests) ?: NULL;
  }

  /**
   * Pushes a request, which becomes the current one.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function push(Request $request) {
    $this->requests[] = $request;
  }

}
