<?php

namespace Drupal\bioland;

/**
 * Keeps the embed media type's add link out of the admin toolbar (BL-1276).
 *
 * admin_toolbar_tools generates an extra link for every media type's add
 * form. The embed one ends up at the toolbar root because its generated
 * parent was not resolved when the menu tree was rebuilt after the type was
 * created. The link is disabled rather than removed so admin_toolbar_tools'
 * own tree stays consistent and /media/add/embed stays reachable.
 *
 * Kept free of Drupal APIs so it is unit-testable without a bootstrap.
 *
 * @see bioland_menu_links_discovered_alter()
 */
final class BiolandToolbarEmbedLink {

  public const LINK_ID = 'admin_toolbar_tools.extra_links:media.add.embed';

  /**
   * Marks the embed add link disabled when it is present.
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
