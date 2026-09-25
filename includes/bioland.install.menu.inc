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
 * Helper function to create the Content menu link.
 *
 * Creates a menu link for quickly adding Content nodes.
 *
 * @return string
 *   Status message.
 */
function _bioland_create_content_menu_link() {
  $logger = \Drupal::logger('bioland');
  $logger->info('Starting Content menu link creation.');

  // Check if MenuLinkContent entity type exists.
  $entity_type_manager = \Drupal::entityTypeManager();
  if (!$entity_type_manager->hasDefinition('menu_link_content')) {
    $logger->warning('MenuLinkContent entity type not available; skipping menu link creation.');
    return t('MenuLinkContent entity type not available; skipping menu link creation.');
  }

  // Use hardcoded parent UUID.
  $known_uuid = '059aed54-bd84-4699-a7c4-5358d5e7c36c';
  $parent_plugin_id = 'menu_link_content:' . $known_uuid;

  $logger->info('Creating Content menu link with hardcoded parent UUID: @uuid', [
    '@uuid' => $known_uuid,
  ]);

  // Verify parent exists before creating the link.
  $parent_link = $entity_type_manager
    ->getStorage('menu_link_content')
    ->loadByProperties(['uuid' => $known_uuid]);
  
  if (empty($parent_link)) {
    $logger->error('Parent menu link with UUID @uuid not found. Cannot create Content menu link.', [
      '@uuid' => $known_uuid,
    ]);
    return t('Error: Parent menu link (UUID: @uuid) not found.', ['@uuid' => $known_uuid]);
  }
  $logger->info('Parent menu link found and verified.');

  // Idempotency (BL-836): update hooks 9048, 9051 and 9057 all call this
  // helper, so without this guard every run added another "Content" link
  // under Publishing > Add.
  $existing = $entity_type_manager
    ->getStorage('menu_link_content')
    ->loadByProperties([
      'title' => 'Content',
      'link__uri' => 'internal:/node/add/content',
      'menu_name' => 'admin',
      'parent' => $parent_plugin_id,
    ]);
  if (!empty($existing)) {
    $logger->info('Content menu link already exists (ID: @id); not creating another.', [
      '@id' => implode(', ', array_keys($existing)),
    ]);
    return t('"Content" menu link already exists (parent: @parent); nothing to create.', [
      '@parent' => $parent_plugin_id,
    ]);
  }

  // Create the menu link in the admin toolbar.
  $menu_link = $entity_type_manager
    ->getStorage('menu_link_content')
    ->create([
      'title' => 'Content',
      'link' => ['uri' => 'internal:/node/add/content'],
      'menu_name' => 'admin',
      'parent' => $parent_plugin_id,
      'weight' => -100,
      'expanded' => FALSE,
      'enabled' => TRUE,
    ]);

  try {
    $menu_link->save();
    $saved_id = $menu_link->id();
    $saved_uuid = $menu_link->uuid();
    $logger->info('Successfully saved Content menu link. ID: @id, UUID: @uuid', [
      '@id' => $saved_id,
      '@uuid' => $saved_uuid,
    ]);

    // Verify the menu link was actually persisted to the database.
    $verification_link = $entity_type_manager
      ->getStorage('menu_link_content')
      ->load($saved_id);

    if ($verification_link === NULL) {
      $logger->error('VERIFICATION FAILED: Content menu link (ID: @id) was not found in database after save. The save() call appeared to succeed but data was not persisted.', [
        '@id' => $saved_id,
      ]);
      return t('Error: Content menu link was not persisted to database after save (ID: @id).', ['@id' => $saved_id]);
    }

    $logger->info('VERIFICATION PASSED: Content menu link confirmed in database.');

    $logger->info('Successfully created Content menu link with ID: @id, UUID: @uuid', [
      '@id' => $saved_id,
      '@uuid' => $saved_uuid,
    ]);
  }
  catch (\Exception $e) {
    $logger->error('Failed to save Content menu link: @message. Parent was: @parent', [
      '@message' => $e->getMessage(),
      '@parent' => $parent_plugin_id,
    ]);
    return t('Error saving Content menu link: @message', ['@message' => $e->getMessage()]);
  }

  // Rebuild caches.
  try {
    \Drupal::service('router.builder')->rebuild();
    $logger->info('Router rebuilt successfully.');
  }
  catch (\Exception $e) {
    $logger->warning('Failed to rebuild router: @message', ['@message' => $e->getMessage()]);
  }

  try {
    \Drupal::cache('menu')->invalidateAll();
    $logger->info('Menu cache invalidated.');
  }
  catch (\Exception $e) {
    $logger->warning('Failed to invalidate menu cache: @message', ['@message' => $e->getMessage()]);
  }

  // Clear menu cache to ensure the new link appears
  if (\Drupal::hasService('plugin.manager.menu.link')) {
    try {
      \Drupal::service('plugin.manager.menu.link')->rebuild();
      $logger->info('Menu link tree rebuilt.');
    }
    catch (\Exception $e) {
      $logger->warning('Failed to rebuild menu link tree: @message', ['@message' => $e->getMessage()]);
    }
  }

  return t('Created "Content" menu link pointing to /node/add/content (parent: @parent).', [
    '@parent' => $parent_plugin_id,
  ]);
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
 * As the highest-numbered hook it runs LAST on every site, so per
 * SearchApiConvergenceHookTest it also re-applies the canonical v2 Search API
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
