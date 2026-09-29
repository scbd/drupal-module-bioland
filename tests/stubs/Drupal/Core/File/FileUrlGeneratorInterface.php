<?php

namespace Drupal\Core\File;

/**
 * Minimal stub of the core file URL generator for unit tests.
 */
interface FileUrlGeneratorInterface {

  public function generateString(string $uri);

  public function generateAbsoluteString(string $uri);

}
