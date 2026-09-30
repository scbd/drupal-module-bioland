<?php

namespace Drupal\bioland;

/**
 * Keeps the llms.txt configuration link out of the admin toolbar (BL-1291).
 *
 * The contrib llms_txt module declares a static menu link that renders at the
 * toolbar root instead of under Content. The link is disabled rather than
 * removed so the menu tree stays consistent and /admin/content/llms-txt stays
 * reachable by URL.
 *
 * Kept free of Drupal APIs so it is unit-testable without a bootstrap.
 *
 * @see bioland_menu_links_discovered_alter()
 */
final class BiolandToolbarLlmsTxtLink {

  public const LINK_ID = 'llms_txt.llms_txt_config';

  /**
   * Marks the llms.txt configuration link disabled when it is present.
   *
   * @param array $links
   *   The discovered menu link definitions, keyed by link id.
   */
  public static function disable(array &$links): void {
    if (isset($links[self::LINK_ID])) {
      $links[self::LINK_ID]['enabled'] = FALSE;
    }
  }

}
