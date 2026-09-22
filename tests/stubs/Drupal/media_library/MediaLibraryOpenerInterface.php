<?php

namespace Drupal\media_library;

use Drupal\Core\Session\AccountInterface;

/**
 * Stub of core's media_library opener contract.
 */
interface MediaLibraryOpenerInterface {

  /**
   * Checks access to the media library for this opener.
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account);

  /**
   * Returns the AJAX response applying the selection.
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids);

}
