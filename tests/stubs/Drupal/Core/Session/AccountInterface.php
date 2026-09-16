<?php

namespace Drupal\Core\Session;

/**
 * Stub interface for a user account.
 */
interface AccountInterface {

  /**
   * Whether the account holds a permission.
   *
   * @param string $permission
   *   The permission.
   *
   * @return bool
   *   TRUE when held.
   */
  public function hasPermission($permission);

  /**
   * The account id.
   *
   * @return int
   *   The uid.
   */
  public function id();

}
