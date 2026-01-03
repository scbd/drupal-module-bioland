<?php

namespace GuzzleHttp;

/**
 * Test stub for GuzzleHttp ClientInterface.
 */
interface ClientInterface {

  /**
   * Send an HTTP request.
   *
   * @param string $method
   *   HTTP method.
   * @param string $uri
   *   URI.
   * @param array $options
   *   Request options.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Response.
   */
  public function request($method, $uri, array $options = []);

}
