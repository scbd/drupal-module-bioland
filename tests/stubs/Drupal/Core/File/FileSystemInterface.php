<?php

namespace Drupal\Core\File;

/**
 * Stub for Drupal\Core\File\FileSystemInterface (constants + method used).
 */
interface FileSystemInterface {

  const CREATE_DIRECTORY = 1;
  const MODIFY_PERMISSIONS = 2;

  /**
   * Checks/creates a directory.
   *
   * @param string $directory
   *   The directory URI.
   * @param int $options
   *   Bitmask of flags.
   *
   * @return bool
   *   TRUE on success.
   */
  public function prepareDirectory($directory, $options = self::MODIFY_PERMISSIONS);

}
