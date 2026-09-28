<?php

namespace Drupal\file;

/**
 * Stub interface for FileRepositoryInterface.
 */
interface FileRepositoryInterface {

  /**
   * Writes data to a destination and returns the resulting file entity.
   *
   * @param string $data
   *   The file contents.
   * @param string $destination
   *   The destination URI.
   * @param int $fileExists
   *   The FileSystemInterface::EXISTS_* behavior.
   *
   * @return \Drupal\file\FileInterface
   *   The saved file entity.
   */
  public function writeData($data, $destination, $fileExists = 1);

}
