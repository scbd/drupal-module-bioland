<?php

namespace Drupal\Core\Access;

/**
 * Stub interface for access results.
 */
interface AccessResultInterface {

  /**
   * Whether access is allowed.
   *
   * @return bool
   *   TRUE when allowed.
   */
  public function isAllowed();

  /**
   * Whether access is explicitly forbidden.
   *
   * @return bool
   *   TRUE when forbidden.
   */
  public function isForbidden();

}
