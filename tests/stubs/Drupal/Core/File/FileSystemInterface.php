<?php

namespace Drupal\Core\File;

/**
 * Stub for Drupal\Core\File\FileSystemInterface (constants + methods used).
 */
interface FileSystemInterface {

  const CREATE_DIRECTORY = 1;
  const MODIFY_PERMISSIONS = 2;
  const EXISTS_REPLACE = 1;

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

  /**
   * Resolves a stream wrapper URI to a local absolute path.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string|false
   *   The realpath, or FALSE.
   */
  public function realpath($uri);

}
