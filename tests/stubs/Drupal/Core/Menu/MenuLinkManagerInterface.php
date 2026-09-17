<?php

namespace Drupal\Core\Menu;

/**
 * Stub interface for the menu link plugin manager.
 *
 * Only the two discovery methods the module uses to validate a requested
 * parent link.
 */
interface MenuLinkManagerInterface {

  /**
   * Tells whether a menu link plugin exists.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return bool
   *   TRUE when the plugin is defined.
   */
  public function hasDefinition($plugin_id);

  /**
   * Returns a menu link plugin definition.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param bool $exception_on_invalid
   *   Whether to throw for an unknown id.
   *
   * @return array|null
   *   The definition, or NULL.
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE);

}
