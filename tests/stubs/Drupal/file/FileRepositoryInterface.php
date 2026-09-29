<?php

namespace Drupal\file;

/**
 * Stub for Drupal\file\FileRepositoryInterface (only writeData()).
 */
interface FileRepositoryInterface {

  /**
   * Writes data to a file and creates a file entity for it.
   *
   * @param string $data
   *   The file contents.
   * @param string $destination
   *   The destination URI.
   *
   * @return \Drupal\file\Entity\File
   *   The saved file entity.
   */
  public function writeData($data, $destination);

}
