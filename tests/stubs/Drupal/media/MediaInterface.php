<?php

namespace Drupal\media;

/**
 * Minimal stub of the media entity interface for unit tests.
 */
interface MediaInterface {

  public function id();

  public function isNew();

  public function bundle();

  public function hasField($field_name);

  public function get($field_name);

  public function getSource();

}
