<?php

namespace Drupal\Core\Entity\Display;

/**
 * Stub interface for EntityViewDisplayInterface.
 */
interface EntityViewDisplayInterface {

  /**
   * Gets the display's field and pseudo-field components.
   *
   * @return array
   *   The display components, keyed by field/pseudo-field name.
   */
  public function getComponents();

}
