<?php

namespace Drupal\file;

/**
 * Stub interface for FileInterface.
 */
interface FileInterface {

  /**
   * @return mixed
   *   The file entity ID.
   */
  public function id();

  /**
   * @return string
   *   The file URI.
   */
  public function getFileUri();

  /**
   * @return string
   *   The filename.
   */
  public function getFilename();

  /**
   * Deletes the file entity.
   */
  public function delete();

}
