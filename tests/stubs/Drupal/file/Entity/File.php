<?php

namespace Drupal\file\Entity;

/**
 * Stub for Drupal\file\Entity\File (only the members this module touches).
 */
class File {

  protected $fid;

  public function __construct($fid = 1) {
    $this->fid = $fid;
  }

  /**
   * Gets the file entity ID.
   *
   * @return int
   */
  public function id() {
    return $this->fid;
  }

}
