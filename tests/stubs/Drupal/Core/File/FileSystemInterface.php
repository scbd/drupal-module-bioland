<?php

namespace Drupal\Core\File;

/**
 * Stub interface for FileSystemInterface.
 */
interface FileSystemInterface {

  const EXISTS_REPLACE = 1;

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
