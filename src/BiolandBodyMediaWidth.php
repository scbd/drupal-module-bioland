<?php

namespace Drupal\bioland;

/**
 * Wrapper class for Body media embedded at a bioland_width_* size preset.
 *
 * Core's media.html.twig emits no view-mode class, so bioland_preprocess_media()
 * adds the class this returns; the Nuxt head and css/bioland.ckeditor.css size
 * that class to the preset share of the body width, whatever the theme (BL-917).
 */
final class BiolandBodyMediaWidth {

  /**
   * Machine-name prefix of the preset media view modes.
   */
  public const VIEW_MODE_PREFIX = 'bioland_width_';

  /**
   * Returns the wrapper class for a preset view mode, NULL for any other.
   *
   * @param string $view_mode
   *   The media view mode, e.g. "bioland_width_50".
   *
   * @return string|null
   *   E.g. "media--view-mode-bioland-width-50".
   */
  public static function viewModeClass(string $view_mode): ?string {
    if (!preg_match('/^' . self::VIEW_MODE_PREFIX . '\d+$/', $view_mode)) {
      return NULL;
    }
    return 'media--view-mode-' . str_replace('_', '-', $view_mode);
  }

}
