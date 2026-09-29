<?php

namespace Drupal\media_library;

/**
 * Stub of core's MediaLibraryUiBuilder (dialog options only).
 */
class MediaLibraryUiBuilder {

  /**
   * Mirrors MediaLibraryUiBuilder::dialogOptions().
   */
  public static function dialogOptions() {
    return [
      'classes' => ['ui-dialog' => 'media-library-widget-modal'],
      'title' => 'Add or select media',
      'height' => '75%',
      'width' => '75%',
    ];
  }

}
