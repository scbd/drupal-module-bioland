<?php

namespace Drupal\bioland\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Gates the Component-menu add flow on the bioland.settings feature flag.
 *
 * WHY THIS IS A ROUTE ACCESS CHECK AND NOT A LOCAL-ACTION ALTER. The
 * "Add Mega Menu component" button is the local action
 * bioland.menu_link_component_add, and core builds each local action's
 * '#access' by running the access manager against the action's own route (see
 * \Drupal\Core\Menu\LocalActionManager::getActionsForRoute()). Denying the
 * route therefore hides the button as a side effect of the single decision,
 * and also blocks anyone typing /add-component directly - a button hidden by
 * hook_menu_local_actions_alter() would leave the URL live.
 *
 * It also keeps the flag cache-correct for free: the AccessResult below
 * carries the bioland.settings cache tag, which the render system bubbles from
 * '#access', so saving the setting refreshes the menu manage screen with no
 * manual cache invalidation. Removing the definition in a local-actions alter
 * would instead need the cached local_action plugin definitions invalidated by
 * hand on every config save.
 *
 * The flag is additive to access, never a widening of it: the route still
 * carries _entity_create_access on menu_link_content, so a user who cannot
 * create menu links is denied whether the flag is on or off.
 *
 * Default is ON when the key is absent, so sites that installed before the
 * flag existed keep the button until an admin turns it off.
 *
 * @see \Drupal\bioland\Form\BiolandAdminSettingsForm
 * @see \Drupal\bioland\Controller\BiolandMenuController::addComponentLink()
 */
class BiolandComponentMenuAccessCheck implements AccessInterface {

  /**
   * The settings object holding the flag.
   */
  const CONFIG_NAME = 'bioland.settings';

  /**
   * The flag key inside that object.
   */
  const FLAG = 'component_menu_add_enabled';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the access check.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Checks whether component menu link authoring is enabled.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed when the flag is on (or absent), forbidden otherwise. The two
   *   outcomes are stated explicitly rather than via allowedIf()/forbiddenIf()
   *   so neither depends on how the access manager folds a neutral result into
   *   the route's other check.
   */
  public function access() {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $result = $config->get(self::FLAG) === FALSE
      ? AccessResult::forbidden('Component menu links are disabled in Bioland admin settings.')
      : AccessResult::allowed();

    return $result->addCacheableDependency($config);
  }

}
