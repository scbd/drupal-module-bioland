<?php

namespace Drupal\Core\Session;

/**
 * Stub interface for Drupal\Core\Session\AccountProxyInterface.
 */
interface AccountProxyInterface {

  /**
   * Gets the currently logged in user.
   */
  public function getAccount();

  /**
   * Gets the id of the user.
   */
  public function id();

}
