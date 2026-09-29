<?php

namespace Drupal\pathauto;

/**
 * Stub of pathauto's PathautoState constants and state key helper.
 */
class PathautoState {

  const SKIP = 0;

  const CREATE = 1;

  /**
   * Mirrors PathautoState::getPathautoStateKey() for short ASCII ids.
   */
  public static function getPathautoStateKey($entity_id) {
    return (string) $entity_id;
  }

}
