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

  // Check if a menu link already exists to avoid duplicates.
  try {
    $existing_links = $entity_type_manager
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'title' => 'Content',
        'link__uri' => 'internal:/node/add/content',
      ]);
  }
  catch (\Exception $e) {
    $logger->error('Failed to query existing menu links: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error querying existing menu links: @message', ['@message' => $e->getMessage()]);
  }

  if (!empty($existing_links)) {
    // Menu link exists, but let's make sure it's enabled and properly configured.
    $link_ids = array_keys($existing_links);
    $logger->info('Content menu link already exists (IDs: @ids); verifying enabled status.', [
      '@ids' => implode(', ', $link_ids),
    ]);
    foreach ($existing_links as $link) {
      if (!$link->isEnabled()) {
        $link->set('enabled', TRUE);
        try {
          $link->save();
          $logger->info('Enabled existing Content menu link (ID: @id).', ['@id' => $link->id()]);
        }
        catch (\Exception $e) {
          $logger->error('Failed to enable existing Content menu link (ID: @id): @message', [
            '@id' => $link->id(),
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
    return t('Content menu link already exists; verified enabled status.');
  }

  // Try to find the "Add content" parent menu link dynamically first.
  $parent_plugin_id = NULL;
  $parent_discovery_method = 'none';

  // Method 1: Try to find by known UUID (most reliable if it exists).
  $known_uuid = '60d958d0-9261-4161-95a9-2ee1970c38c0';
  try {
    $parent_by_uuid = $entity_type_manager
      ->getStorage('menu_link_content')
      ->loadByProperties(['uuid' => $known_uuid]);
    if (!empty($parent_by_uuid)) {
      $parent_plugin_id = 'menu_link_content:' . $known_uuid;
      $parent_discovery_method = 'uuid';
      $logger->info('Found parent menu link by UUID: @uuid', ['@uuid' => $known_uuid]);
    }
    else {
      $logger->notice('Parent menu link not found by UUID: @uuid. Will try alternative methods.', [
        '@uuid' => $known_uuid,
      ]);
    }
  }
  catch (\Exception $e) {
    $logger->warning('Error searching for parent by UUID @uuid: @message', [
      '@uuid' => $known_uuid,
      '@message' => $e->getMessage(),
    ]);
  }

  // Method 2: Try to find "Add content" link by title in admin menu.
  if ($parent_plugin_id === NULL) {
    try {
      $add_content_links = $entity_type_manager
        ->getStorage('menu_link_content')
        ->loadByProperties([
          'title' => 'Add content',
          'menu_name' => 'admin',
        ]);
      if (!empty($add_content_links)) {
        $first_link = reset($add_content_links);
        $parent_plugin_id = 'menu_link_content:' . $first_link->uuid();
        $parent_discovery_method = 'title_search';
        $logger->info('Found parent menu link by title search. UUID: @uuid, ID: @id', [
          '@uuid' => $first_link->uuid(),
          '@id' => $first_link->id(),
        ]);
      }
      else {
        $logger->notice('No "Add content" menu link found by title search in admin menu.');
      }
    }
    catch (\Exception $e) {
      $logger->warning('Error searching for parent by title: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  // Method 3: Try system menu link (Drupal core "Add content").
  if ($parent_plugin_id === NULL) {
    $system_parent = 'system.admin_content';
    $logger->notice('No custom parent found; using system parent: @parent', [
      '@parent' => $system_parent,
    ]);
    $parent_plugin_id = $system_parent;
    $parent_discovery_method = 'system_fallback';
  }

  $logger->info('Creating Content menu link with parent: @parent (method: @method)', [
    '@parent' => $parent_plugin_id,
    '@method' => $parent_discovery_method,
  ]);

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

    // Double-check by querying with properties.
    $verified_by_props = $entity_type_manager
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'title' => 'Content',
        'link__uri' => 'internal:/node/add/content',
      ]);

    if (empty($verified_by_props)) {
      $logger->error('VERIFICATION FAILED: Content menu link not found by properties query after save. ID load succeeded but property search failed. Possible data corruption.', [
        '@id' => $saved_id,
        '@uuid' => $saved_uuid,
      ]);
      return t('Error: Content menu link verification by properties failed (ID: @id).', ['@id' => $saved_id]);
    }

    $logger->info('VERIFICATION PASSED: Content menu link confirmed in database. Found @count matching link(s).', [
      '@count' => count($verified_by_props),
    ]);
  }
  catch (\Exception $e) {
    $logger->error('Failed to save Content menu link: @message. Parent was: @parent (method: @method)', [
      '@message' => $e->getMessage(),
      '@parent' => $parent_plugin_id,
      '@method' => $parent_discovery_method,
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

  if (\Drupal::hasService('menu.link_tree')) {
    try {
      \Drupal::service('menu.link_tree')->rebuild();
      $logger->info('Menu link tree rebuilt.');
    }
    catch (\Exception $e) {
      $logger->warning('Failed to rebuild menu link tree: @message', ['@message' => $e->getMessage()]);
    }
  }

  return t('Created "Content" menu link pointing to /node/add/content (parent: @parent, method: @method).', [
    '@parent' => $parent_plugin_id,
    '@method' => $parent_discovery_method,
  ]);
}

/**
 * Helper function to update menu item 139 URL.
 *
 * Changes the URL of menu_link_content entity with ID 139 to /admin/content/media.
 *
 * @return string
 *   Status message.
 */
function _bioland_update_menu_item_139() {
  $entity_type_manager = \Drupal::entityTypeManager();
  
  if (!$entity_type_manager->hasDefinition('menu_link_content')) {
    return t('MenuLinkContent entity type not available; skipping menu item update.');
  }
  
  try {
    $menu_link = $entity_type_manager
      ->getStorage('menu_link_content')
      ->load(139);
    
    if (!$menu_link) {
      return t('Menu item with ID 139 not found.');
    }
    
    $old_url = $menu_link->get('link')->uri ?? 'unknown';
    $menu_link->set('link', ['uri' => 'internal:/admin/content/media']);
    $menu_link->save();
    
    // Clear menu caches only - router rebuild happens at end of install.
    \Drupal::cache('menu')->invalidateAll();
    
    \Drupal::logger('bioland')->notice('Updated menu item 139 URL from @old to /admin/content/media.', [
      '@old' => $old_url,
    ]);
    return t('Updated menu item 139 URL from @old to /admin/content/media.', [
      '@old' => $old_url,
    ]);
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to update menu item 139: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error updating menu item 139: @message', ['@message' => $e->getMessage()]);
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
