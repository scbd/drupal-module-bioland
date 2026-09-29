<?php

/**
 * @file
 * Menu and navigation configuration functions for the Bioland module.
 *
 * This file contains helper functions and update hooks related to:
 * - Admin menu configuration
 * - Menu link management
 * - Main menu lock feature
 * - Content type available menus
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS
 * =============================================================================
 */

/**
 * Helper function to enable Main Menu Lock and remove permissions.
 *
 * Sets main_menu_lock config to TRUE and removes "administer main menu items"
 * permission from scbd_staff and site_manager roles.
 *
 * @return string
 *   Status message.
 */
function _bioland_enable_main_menu_lock() {
  $messages = [];
  
  // Set main_menu_lock to TRUE in config.
  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  $config->set('main_menu_lock', TRUE);
  $config->save(TRUE);
  $messages[] = t('Enabled Main Menu Lock in configuration.');
  
  // Remove "administer main menu items" permission from specific roles.
  $permission = 'administer main menu items';
  $roles_to_lock = ['scbd_staff', 'site_manager'];
  
  try {
    $role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
    
    foreach ($roles_to_lock as $role_id) {
      $role = $role_storage->load($role_id);
      
      if ($role) {
        if ($role->hasPermission($permission)) {
          $role->revokePermission($permission);
          $role->save();
          $messages[] = t('Removed "@permission" permission from @role role.', [
            '@permission' => $permission,
            '@role' => $role_id,
          ]);
          \Drupal::logger('bioland')->info('Main Menu Lock: Removed "@permission" from @role.', [
            '@permission' => $permission,
            '@role' => $role_id,
          ]);
        }
        else {
          $messages[] = t('Role @role does not have "@permission" permission (already removed).', [
            '@role' => $role_id,
            '@permission' => $permission,
          ]);
        }
      }
      else {
        $messages[] = t('Role @role not found, skipping permission removal.', [
          '@role' => $role_id,
        ]);
        \Drupal::logger('bioland')->warning('Main Menu Lock: Role @role not found.', [
          '@role' => $role_id,
        ]);
      }
    }
  }
  catch (\Exception $e) {
    $messages[] = t('Error managing Main Menu Lock permissions: @message', [
      '@message' => $e->getMessage(),
    ]);
    \Drupal::logger('bioland')->error('Main Menu Lock error: @message', [
      '@message' => $e->getMessage(),
    ]);
  }
  
  return implode("\n", $messages);
}

/**
 * Retired helper that used to create Publishing > Add > Content.
 *
 * BL-836: Publishing now carries only "Content (pages)" and
 * "Media (attachments)", so the /node/add/content link under "Add" is no
 * longer created. Kept as a no-op because update hooks 9048, 9051 and 9057
 * still call it; bioland_update_9092() removes links created earlier.
 *
 * @return string
 *   Status message.
 */
function _bioland_create_content_menu_link() {
  return t('Publishing > Add > Content menu link is retired (BL-836); nothing created.');
}

/**
 * Helper function to disable auto_node_translate menu items.
 *
 * Hides translation-related admin menu items via hook_menu_links_discovered_alter().
 * System menu links cannot be disabled programmatically, so we use the hook
 * to remove them from menu discovery.
 *
 * @return string
 *   Status message.
 */
function _bioland_disable_translation_menus() {
  $logger = \Drupal::logger('bioland');
  $logger->info('Starting translation menu disable process.');
  
  // System-defined menu items to hide are handled by hook_menu_links_discovered_alter()
  // in bioland.module. This function just rebuilds the menu cache to apply the changes.
  $menus_to_hide = [
    'auto_node_translate.translators',
    'auto_node_translate_amazon.settings',
    'auto_node_translate_deepl.settings',
    'auto_node_translate.my_memory_settings',
  ];
  
  $logger->info('Hiding auto_node_translate menu items via hook_menu_links_discovered_alter(): @items', [
    '@items' => implode(', ', $menus_to_hide),
  ]);
  
  // Clear menu caches and rebuild menu links.
  try {
    // Clear the menu cache.
    \Drupal::cache('menu')->invalidateAll();
    
    // Rebuild menu links to apply hook_menu_links_discovered_alter().
    if (\Drupal::hasService('plugin.manager.menu.link')) {
      \Drupal::service('plugin.manager.menu.link')->rebuild();
    }
    
    // Clear router cache to ensure menu routes are updated.
    if (\Drupal::hasService('router.builder')) {
      \Drupal::service('router.builder')->rebuild();
    }
    
    $logger->info('Menu caches cleared and menu links rebuilt successfully.');
  }
  catch (\Exception $e) {
    $logger->error('Failed to rebuild menu links: @message', ['@message' => $e->getMessage()]);
    return t('Failed to hide translation menus: @message', ['@message' => $e->getMessage()]);
  }
  
  $summary = t('Translation menu items hidden successfully. Menu links have been rebuilt.');
  $logger->info('Translation menu disable process completed successfully.');
  return $summary;
}

/**
 * Helper function to update menu item 139 URL.
 *
 * Changes the URL of menu_link_content entity with ID 139 to /en/media/add.
 *
 * @return string
 *   Status message.
 */
function _bioland_update_menu_item_139() {
  $entity_type_manager = \Drupal::entityTypeManager();
  
  if (!$entity_type_manager->hasDefinition('menu_link_content')) {
    return t('MenuLinkContent entity type not available; skipping menu item update.');
  }
  
  $messages = [];
  
  try {
    // Update menu item 139
    $menu_link = $entity_type_manager
      ->getStorage('menu_link_content')
      ->load(139);
    
    if (!$menu_link) {
      $messages[] = t('Menu item with ID 139 not found.');
    }
    else {
      $old_url = $menu_link->get('link')->uri ?? 'unknown';
      $menu_link->set('link', ['uri' => 'internal:/en/media/add']);
      $menu_link->save();
      
      \Drupal::logger('bioland')->notice('Updated menu item 139 URL from @old to /en/media/add.', [
        '@old' => $old_url,
      ]);
      $messages[] = t('Updated menu item 139 URL from @old to /en/media/add.', [
        '@old' => $old_url,
      ]);
    }
    
    // Update menu item 133
    $menu_link_133 = $entity_type_manager
      ->getStorage('menu_link_content')
      ->load(133);
    
    if (!$menu_link_133) {
      $messages[] = t('Menu item with ID 133 not found.');
    }
    else {
      $old_url_133 = $menu_link_133->get('link')->uri ?? 'unknown';
      $menu_link_133->set('link', ['uri' => 'internal:/admin/content/media']);
      $menu_link_133->save();
      
      \Drupal::logger('bioland')->notice('Updated menu item 133 URL from @old to /admin/content/media.', [
        '@old' => $old_url_133,
      ]);
      $messages[] = t('Updated menu item 133 URL from @old to /admin/content/media.', [
        '@old' => $old_url_133,
      ]);
    }
    
    // Update menu item 136
    $menu_link_136 = $entity_type_manager
      ->getStorage('menu_link_content')
      ->load(136);
    
    if (!$menu_link_136) {
      $messages[] = t('Menu item with ID 136 not found.');
    }
    else {
      $old_url_136 = $menu_link_136->get('link')->uri ?? 'unknown';
      $menu_link_136->set('link', ['uri' => 'internal:/admin/content/forum-topics']);
      $menu_link_136->save();
      
      \Drupal::logger('bioland')->notice('Updated menu item 136 URL from @old to /admin/content/forum-topics.', [
        '@old' => $old_url_136,
      ]);
      $messages[] = t('Updated menu item 136 URL from @old to /admin/content/forum-topics.', [
        '@old' => $old_url_136,
      ]);
    }
    
    // Clear menu caches only - router rebuild happens at end of install.
    \Drupal::cache('menu')->invalidateAll();
    
    return implode(' ', $messages);
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to update menu items: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error updating menu items: @message', ['@message' => $e->getMessage()]);
  }
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Configure available menus for content type.
 */
function bioland_update_9056() {
  return _bioland_configure_content_type_available_menus();
}

/**
 * Enable Main Menu Lock feature and remove main menu permissions from specific roles.
 */
function bioland_update_9040() {
  return _bioland_enable_main_menu_lock();
}

/**
 * Create 'Content' menu link programmatically.
 */
function bioland_update_9048() {
  return _bioland_create_content_menu_link();
}

/**
 * Delete and recreate Content menu link with proper parent discovery.
 */
function bioland_update_9051() {
  // First, delete any existing broken menu links.
  $entity_type_manager = \Drupal::entityTypeManager();
  if ($entity_type_manager->hasDefinition('menu_link_content')) {
    $existing_links = $entity_type_manager
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'title' => 'Content',
        'link__uri' => 'internal:/node/add/content',
      ]);
    
    foreach ($existing_links as $link) {
      $link->delete();
    }
  }
  
  // Now create fresh with proper parent discovery.
  return _bioland_create_content_menu_link();
}

/**
 * Update menu item ID 139 URL to /admin/content/media.
 */
function bioland_update_9052() {
  $result = _bioland_update_menu_item_139();
  
  // Rebuild router after menu URL change (not done in helper to avoid double-rebuild during install).
  if (\Drupal::hasService('router.builder')) {
    \Drupal::service('router.builder')->rebuild();
  }
  
  return $result;
}

/**
 * Create Content menu link with fixed parent - retry without duplicate checks.
 */
function bioland_update_9057() {
  return _bioland_create_content_menu_link();
}

/**
 * Disable auto_node_translate admin menu items.
 */
function bioland_update_9058() {
  return _bioland_disable_translation_menus();
}

/**
 * Remove duplicate Publishing admin menu links (BL-836).
 *
 * Sites that ran update hooks 9048, 9051 and 9057 got one "Content" link
 * (/node/add/content) per run under Publishing > Add, and some also carry
 * copies of "Content (pages)" / "Media (attachments)" under "Add". This hook
 * loads every menu_link_content in the 'admin' menu, asks
 * \Drupal\bioland\BiolandAdminMenuDedupe which ones are duplicates (exact
 * title + link uri matching) and deletes only those, logging each deletion.
 * Children of a deleted link are re-parented to the surviving copy; a link
 * with children and no surviving copy is kept and a warning is logged.
 * A clean site deletes nothing, so it is safe to run more than once.
 *
 * It was the highest-numbered hook when added (bioland_update_9092() now is),
 * so per SearchApiConvergenceHookTest it also re-applies the canonical v2 Search API
 * config.
 *
 * @see \Drupal\bioland\BiolandAdminMenuDedupe
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9087() {
  $messages = [];
  $logger = \Drupal::logger('bioland');
  $entity_type_manager = \Drupal::entityTypeManager();

  if (!$entity_type_manager->hasDefinition('menu_link_content')) {
    $messages[] = 'MenuLinkContent entity type not available; skipped admin menu de-duplication.';
  }
  else {
    // Load/delete failures throw UpdateException so `drush updb` reports the
    // hook as failed and retries it, instead of marking a partial run done.
    try {
      $storage = $entity_type_manager->getStorage('menu_link_content');
      $links = $storage->loadByProperties(['menu_name' => 'admin']);

      $descriptors = [];
      foreach ($links as $id => $link) {
        $descriptors[] = [
          'id' => (int) $id,
          'title' => (string) $link->get('title')->value,
          'uri' => (string) ($link->get('link')->uri ?? ''),
          'parent' => (string) $link->get('parent')->value,
          'enabled' => (bool) $link->get('enabled')->value,
        ];
      }

      $deleted = 0;
      foreach (\Drupal\bioland\BiolandAdminMenuDedupe::plan($descriptors) as $id => $survivor_id) {
        if (!isset($links[$id])) {
          continue;
        }
        $link = $links[$id];

        // Children still point at the deleted link's uuid in their own
        // 'parent' field; move them to the surviving copy, or keep the link
        // when there is nowhere safe to move them.
        $children = $storage->loadByProperties(['parent' => 'menu_link_content:' . $link->uuid()]);
        if (!empty($children)) {
          if ($survivor_id === NULL || !isset($links[$survivor_id])) {
            $logger->warning('BL-836: kept duplicate admin menu link @id "@title": it has @count child link(s) and no surviving copy to move them to.', [
              '@id' => $id,
              '@title' => $link->get('title')->value,
              '@count' => count($children),
            ]);
            continue;
          }
          $new_parent = 'menu_link_content:' . $links[$survivor_id]->uuid();
          foreach ($children as $child_id => $child) {
            $logger->notice('BL-836: moving admin menu link @child from duplicate @id to surviving copy @survivor.', [
              '@child' => $child_id,
              '@id' => $id,
              '@survivor' => $survivor_id,
            ]);
            $child->set('parent', $new_parent);
            $child->save();
          }
        }

        $logger->notice('BL-836: deleting duplicate admin menu link @id "@title" (@uri, parent @parent).', [
          '@id' => $id,
          '@title' => $link->get('title')->value,
          '@uri' => $link->get('link')->uri ?? '',
          '@parent' => $link->get('parent')->value,
        ]);
        $link->delete();
        $deleted++;
      }
    }
    catch (\Exception $e) {
      $logger->error('BL-836: admin menu de-duplication failed: @message', ['@message' => $e->getMessage()]);
      throw new \Drupal\Core\Utility\UpdateException('BL-836: admin menu de-duplication failed: ' . $e->getMessage(), 0, $e);
    }

    $messages[] = sprintf('Admin menu de-duplication: checked %d links, deleted %d duplicates.', count($descriptors), $deleted);

    // A cache/menu rebuild failure must not hide the deleted count above.
    try {
      \Drupal::cache('menu')->invalidateAll();
      if (\Drupal::hasService('plugin.manager.menu.link')) {
        \Drupal::service('plugin.manager.menu.link')->rebuild();
      }
    }
    catch (\Exception $e) {
      $logger->error('BL-836: menu cache rebuild after de-duplication failed: @message', ['@message' => $e->getMessage()]);
      $messages[] = 'Menu cache rebuild failed: ' . $e->getMessage();
    }
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Retire Publishing > Add > Content, leaving two Publishing items (BL-836).
 *
 * Publishing should show only "Content (pages)" and "Media (attachments)".
 * This deletes every "Content" -> /node/add/content link under "Add", then
 * deletes the "Add" parent itself once it has no children left (a site that
 * added its own links under "Add" keeps it). A link that has children of its
 * own is kept and a warning is logged. Safe to run more than once.
 *
 * As the highest-numbered hook it runs LAST on every site, so per
 * SearchApiConvergenceHookTest it also re-applies the canonical v2 Search API
 * config.
 *
 * @see \Drupal\bioland\BiolandAdminMenuDedupe::retiredAddLinkIds()
 */
function bioland_update_9092() {
  $messages = [];
  $logger = \Drupal::logger('bioland');
  $entity_type_manager = \Drupal::entityTypeManager();

  if (!$entity_type_manager->hasDefinition('menu_link_content')) {
    $messages[] = 'MenuLinkContent entity type not available; skipped Publishing > Add clean-up.';
  }
  else {
    try {
      $storage = $entity_type_manager->getStorage('menu_link_content');
      $add_parent = \Drupal\bioland\BiolandAdminMenuDedupe::ADD_PARENT;

      $links = $storage->loadByProperties(['menu_name' => 'admin', 'parent' => $add_parent]);
      $descriptors = [];
      foreach ($links as $id => $link) {
        $descriptors[] = [
          'id' => (int) $id,
          'title' => (string) $link->get('title')->value,
          'uri' => (string) ($link->get('link')->uri ?? ''),
          'parent' => (string) $link->get('parent')->value,
        ];
      }

      $deleted = 0;
      foreach (\Drupal\bioland\BiolandAdminMenuDedupe::retiredAddLinkIds($descriptors) as $id) {
        $link = $links[$id];
        if (!empty($storage->loadByProperties(['parent' => 'menu_link_content:' . $link->uuid()]))) {
          $logger->warning('BL-836: kept Publishing > Add > Content link @id: it has child links.', ['@id' => $id]);
          continue;
        }
        $logger->notice('BL-836: deleting retired Publishing > Add > Content link @id.', ['@id' => $id]);
        $link->delete();
        $deleted++;
      }
      $messages[] = sprintf('Deleted %d Publishing > Add > Content link(s).', $deleted);

      // Drop the "Add" parent once nothing is left under it.
      $add_uuid = substr($add_parent, strlen('menu_link_content:'));
      $add_links = $storage->loadByProperties(['uuid' => $add_uuid]);
      if (!empty($add_links) && empty($storage->loadByProperties(['parent' => $add_parent]))) {
        foreach ($add_links as $id => $add_link) {
          $logger->notice('BL-836: deleting empty Publishing > Add menu link @id.', ['@id' => $id]);
          $add_link->delete();
        }
        $messages[] = 'Deleted the empty Publishing > Add menu link.';
      }
    }
    catch (\Exception $e) {
      $logger->error('BL-836: Publishing > Add clean-up failed: @message', ['@message' => $e->getMessage()]);
      throw new \Drupal\Core\Utility\UpdateException('BL-836: Publishing > Add clean-up failed: ' . $e->getMessage(), 0, $e);
    }

    try {
      \Drupal::cache('menu')->invalidateAll();
      if (\Drupal::hasService('plugin.manager.menu.link')) {
        \Drupal::service('plugin.manager.menu.link')->rebuild();
      }
    }
    catch (\Exception $e) {
      $logger->error('BL-836: menu cache rebuild after Publishing > Add clean-up failed: @message', ['@message' => $e->getMessage()]);
      $messages[] = 'Menu cache rebuild failed: ' . $e->getMessage();
    }
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
