<?php

namespace Drupal\Core\Security;

/**
 * Stub of core's TrustedCallbackInterface.
 */
interface TrustedCallbackInterface {

  /**
   * Lists the methods safe to use as render callbacks.
   *
   * @return string[]
   *   Method names.
   */
  public static function trustedCallbacks();

}
