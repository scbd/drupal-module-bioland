<?php

namespace Drupal\system;

/**
 * Stub interface for the Menu config entity.
 *
 * Only the accessors the Bioland controller uses are declared.
 */
interface MenuInterface {

  /**
   * Gets the menu machine name.
   *
   * @return string
   *   The menu id, e.g. "main".
   */
  public function id();

  /**
   * Gets the human-readable menu label.
   *
   * @return string
   *   The menu label.
   */
  public function label();

}
