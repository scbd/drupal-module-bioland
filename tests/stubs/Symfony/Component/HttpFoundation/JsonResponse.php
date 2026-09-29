<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Stub for Symfony's JsonResponse.
 */
class JsonResponse {

  /**
   * The response headers.
   *
   * @var \Symfony\Component\HttpFoundation\HeaderBag
   */
  public $headers;

  protected $data;
  protected $status;

  public function __construct($data = NULL, int $status = 200) {
    $this->data = $data;
    $this->status = $status;
    $this->headers = new HeaderBag();
  }

  public function getStatusCode() {
    return $this->status;
  }

  public function getContent() {
    return json_encode($this->data);
  }

}
