<?php

/**
 * @file
 * Content Type & Taxonomy configuration functions for the Bioland module.
 *
 * This file contains helper functions and update hooks related to:
 * - Content type menu configuration
 * - Content type taxonomy terms
 * - Content type status by site type (BL2 vs Biosafety Land)
 * - System pages search terms
 * - Content type weights
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS
 * =============================================================================
 */

/**
 * Helper function to configure available menus for content type.
 *
 * Sets the available menus for the 'content' node type.
 * Only applies to BL2 sites (where is_biosafety_land = false).
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_content_type_available_menus() {
  // Only configure menus for BL2 sites (is_biosafety_land = false).
  $config_factory = \Drupal::configFactory();
  $bioland_config = $config_factory->get('bioland.settings');
  $is_biosafety_land = $bioland_config->get('is_biosafety_land');
  
  if ($is_biosafety_land !== FALSE) {
    return t('Skipping menu configuration - not a BL2 site.');
  }
  
  $node_type_config = $config_factory->getEditable('node.type.content');
  
  if ($node_type_config->isNew()) {
    return t('Content node type not found; skipping menu configuration.');
  }
  
  // Available menus for BL2 sites.
  $available_menus = [
    'biodiversity-facts',
    'convention-protocols',
    'cooperation',
    'footer',
    'footer-credits',
    'implementation',
    'news-updates',
    'resources',
    'our-targets',
  ];
  
  // Set the available menus in third_party_settings.
  $node_type_config->set('third_party_settings.menu_ui.available_menus', $available_menus);
  $node_type_config->save(TRUE);
  
  $menu_list = implode(', ', $available_menus);
  \Drupal::logger('bioland')->notice('Configured available menus for content type (BL2): @menus', [
    '@menus' => $menu_list,
  ]);
  
  return t('Configured @count available menus for content type (BL2): @menus', [
    '@count' => count($available_menus),
    '@menus' => $menu_list,
  ]);
}

/**
 * Helper function to configure content type taxonomy terms.
 *
 * Creates or updates taxonomy terms in the 'tags' vocabulary to match
 * the predefined content types. Preserves term IDs and marks new terms as disabled.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_content_types() {
  $content_types = [
    ['tid' => 2, 'name' => 'News', 'field_plural' => 'News', 'icon' => '📰'],
    ['tid' => 3, 'name' => 'Event', 'field_plural' => 'Events', 'icon' => '📅'],
    ['tid' => 4, 'name' => 'Learning Resource', 'field_plural' => 'Learning Resources', 'icon' => '🎓'],
    ['tid' => 5, 'name' => 'Project', 'field_plural' => 'Projects', 'icon' => '📊'],
    ['tid' => 6, 'name' => 'Basic Page', 'field_plural' => 'Basic Pages', 'icon' => '📄'],
    ['tid' => 54, 'name' => 'Article', 'field_plural' => 'Articles', 'icon' => '📄'],
    ['tid' => 8, 'name' => 'Government Ministry or Institute', 'field_plural' => 'Government Ministries or Institutes', 'icon' => '🏛️'],
    ['tid' => 9, 'name' => 'Ecosystem', 'field_plural' => 'Ecosystems', 'icon' => '🌍'],
    ['tid' => 10, 'name' => 'Protected Area', 'field_plural' => 'Protected Areas', 'icon' => '🏞️'],
    ['tid' => 11, 'name' => 'Biodiversity Data', 'field_plural' => 'Biodiversity Data', 'icon' => '📈'],
    ['tid' => 12, 'name' => 'Document', 'field_plural' => 'Documents', 'icon' => '📋'],
    ['tid' => 13, 'name' => 'Related Website', 'field_plural' => 'Related Websites', 'icon' => '🔗'],
    ['tid' => 15, 'name' => 'Other', 'field_plural' => 'Others', 'icon' => '⚙️'],
    ['tid' => 16, 'name' => 'Image or Video', 'field_plural' => 'Images or Videos', 'icon' => '🎬'],
    ['tid' => 55, 'name' => 'Other Resource', 'field_plural' => 'Other Resources', 'icon' => '⚙️'],
    ['tid' => 43, 'name' => 'FAQ', 'field_plural' => 'FAQs', 'icon' => '❓'],
    ['tid' => 44, 'name' => 'National Information', 'field_plural' => 'National Informations', 'icon' => '🏴'],
    ['tid' => 45, 'name' => 'Status of LMO', 'field_plural' => 'Status of LMOs', 'icon' => '🧬'],
    ['tid' => 46, 'name' => 'Field Trial', 'field_plural' => 'Field Trials', 'icon' => '🌱'],
    ['tid' => 47, 'name' => 'National Mainstreaming Strategy', 'field_plural' => 'National Mainstreaming Strategies', 'icon' => '🗂️'],
    ['tid' => 48, 'name' => 'Capacity Building', 'field_plural' => 'Capacity Building', 'icon' => '🔧'],
    ['tid' => 49, 'name' => 'Announcement', 'field_plural' => 'Announcements', 'icon' => '📢'],
    ['tid' => 50, 'name' => 'Contact', 'field_plural' => 'Contacts', 'icon' => '📞'],
  ];

  $created = 0;
  $updated = 0;
  $errors = 0;

  foreach ($content_types as $content_type) {
    try {
      // Try to load existing term by tid.
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($content_type['tid']);

      $is_new = FALSE;
      
      if (!$term) {
        // Create new term with specific tid.
        $term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->create([
            'vid' => 'tags',
            'tid' => $content_type['tid'],
            'status' => 0, // Mark new terms as disabled.
          ]);
        $is_new = TRUE;
      }

      // Update term fields.
      $term->setName($content_type['name']);
      
      // Set custom fields if they exist.
      if ($term->hasField('field_plural')) {
        $term->set('field_plural', $content_type['field_plural']);
      }
      
      if ($term->hasField('field_icon')) {
        $term->set('field_icon', $content_type['icon']);
      }
      
      // Ensure new terms are ALWAYS disabled (status = 0).
      // This applies to newly created terms only - existing terms keep their
      // current status to preserve admin-enabled content types.
      if ($is_new) {
        $term->set('status', 0);
        \Drupal::logger('bioland')->notice('Setting new term @tid (@name) to disabled status.', [
          '@tid' => $content_type['tid'],
          '@name' => $content_type['name'],
        ]);
      }
      
      $term->save();
      
      if ($is_new) {
        $created++;
        \Drupal::logger('bioland')->notice('Created taxonomy term @tid: @name (disabled)', [
          '@tid' => $content_type['tid'],
          '@name' => $content_type['name'],
        ]);
      }
      else {
        $updated++;
      }
    }
    catch (\Exception $e) {
      $errors++;
      \Drupal::logger('bioland')->error('Error configuring taxonomy term @tid (@name): @message', [
        '@tid' => $content_type['tid'],
        '@name' => $content_type['name'],
        '@message' => $e->getMessage(),
      ]);
    }
  }

  $message = t('Configured content type taxonomy terms: @created created (disabled), @updated updated, @errors errors.', [
    '@created' => $created,
    '@updated' => $updated,
    '@errors' => $errors,
  ]);
  
  \Drupal::logger('bioland')->notice($message);
  
  return $message;
}

/**
 * Configure content type taxonomy term status based on site type.
 *
 * Enables/disables taxonomy terms in the 'tags' vocabulary based on whether
 * the site is a Biosafety Land instance or a BL2 (Bioland) instance.
 *
 * - Biosafety Land sites: Disable BL2-specific terms (4, 6, 8, 9, 10, 11, 15)
 * - BL2 sites: Disable Biosafety-specific terms (55, 43, 44, 45, 46, 47, 48, 49, 50)
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_content_type_status_by_site_type() {
  // Get the is_biosafety_land setting.
  $config = \Drupal::config('bioland.settings');
  $is_biosafety_land = $config->get('is_biosafety_land') ?: FALSE;

  // BL2-specific content types (disable on Biosafety Land sites).
  $bl2_content_types = [
    4,  // Learning Resource
    6,  // Basic Page
    8,  // Government Ministry or Institute
    9,  // Ecosystem
    10, // Protected Area
    11, // Biodiversity Data
    15, // Other
  ];

  // Biosafety-specific content types (disable on BL2 sites).
  $biosafety_content_types = [
    55, // Other Resource
    43, // FAQ
    44, // National Information
    45, // Status of LMO
    46, // Field Trial
    47, // National Mainstreaming Strategy
    48, // Capacity Building
    49, // Announcement
    50, // Contact
  ];

  // Determine which terms to enable and disable.
  if ($is_biosafety_land) {
    $terms_to_disable = $bl2_content_types;
    $terms_to_enable = $biosafety_content_types;
    $site_type = 'Biosafety Land';
  }
  else {
    $terms_to_disable = $biosafety_content_types;
    $terms_to_enable = $bl2_content_types;
    $site_type = 'BL2 (Bioland)';
  }

  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $disabled_count = 0;
  $enabled_count = 0;
  $errors = 0;

  // Disable terms not applicable to this site type.
  foreach ($terms_to_disable as $tid) {
    try {
      $term = $term_storage->load($tid);
      if ($term && $term->get('status')->value != 0) {
        $term->set('status', 0);
        $term->save();
        $disabled_count++;
        \Drupal::logger('bioland')->notice('Disabled content type term @tid (@name) for @site_type site.', [
          '@tid' => $tid,
          '@name' => $term->label(),
          '@site_type' => $site_type,
        ]);
      }
    }
    catch (\Exception $e) {
      $errors++;
      \Drupal::logger('bioland')->error('Error disabling term @tid: @message', [
        '@tid' => $tid,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  // Enable terms applicable to this site type.
  foreach ($terms_to_enable as $tid) {
    try {
      $term = $term_storage->load($tid);
      if ($term && $term->get('status')->value != 1) {
        $term->set('status', 1);
        $term->save();
        $enabled_count++;
        \Drupal::logger('bioland')->notice('Enabled content type term @tid (@name) for @site_type site.', [
          '@tid' => $tid,
          '@name' => $term->label(),
          '@site_type' => $site_type,
        ]);
      }
    }
    catch (\Exception $e) {
      $errors++;
      \Drupal::logger('bioland')->error('Error enabling term @tid: @message', [
        '@tid' => $tid,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  $message = t('Configured content type terms for @site_type: @enabled enabled, @disabled disabled, @errors errors.', [
    '@site_type' => $site_type,
    '@enabled' => $enabled_count,
    '@disabled' => $disabled_count,
    '@errors' => $errors,
  ]);

  \Drupal::logger('bioland')->notice($message);

  return $message;
}

/**
 * Configure system_pages search taxonomy terms.
 *
 * Creates "Biosafety Clearing-House Search" and "Access And Benefit-sharing
 * Clearing-house Search" terms with proper parent relationships.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_system_pages_search_terms() {
  $messages = [];
  $created = 0;
  $updated = 0;
  $errors = 0;
  
  // First ensure parent term exists (tid 23 should exist).
  $parent_term = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->load(23);
  
  if (!$parent_term) {
    return t('Parent term tid 23 not found. Cannot create search terms.');
  }
  
  // Define the search terms with their parent relationships.
  $search_terms = [
    [
      'tid' => 52,
      'name' => 'Biosafety Clearing-House Search',
      'parent' => 23,
    ],
    [
      'tid' => 53,
      'name' => 'Access And Benefit-sharing Clearing-house Search',
      'parent' => 52, // Parent is the BCH Search term
    ],
  ];
  
  foreach ($search_terms as $term_data) {
    try {
      // Try to load existing term by tid.
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($term_data['tid']);
      
      $is_new = FALSE;
      
      if (!$term) {
        // Create new term with specific tid.
        $term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->create([
            'vid' => 'system_pages',
            'tid' => $term_data['tid'],
            'status' => 1, // Published
          ]);
        $is_new = TRUE;
      }
      
      // Update term fields.
      $term->setName($term_data['name']);
      
      // Set parent relationship.
      if (isset($term_data['parent'])) {
        // Load parent to verify it exists.
        $parent = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->load($term_data['parent']);
        
        if ($parent) {
          $term->set('parent', [['target_id' => $term_data['parent']]]);
        }
      }
      
      $term->save();
      
      if ($is_new) {
        $created++;
        $messages[] = t('Created system_pages term @tid: @name', [
          '@tid' => $term_data['tid'],
          '@name' => $term_data['name'],
        ]);
      }
      else {
        $updated++;
        $messages[] = t('Updated system_pages term @tid: @name', [
          '@tid' => $term_data['tid'],
          '@name' => $term_data['name'],
        ]);
      }
    }
    catch (\Exception $e) {
      $errors++;
      $messages[] = t('Error configuring system_pages term @tid (@name): @message', [
        '@tid' => $term_data['tid'],
        '@name' => $term_data['name'],
        '@message' => $e->getMessage(),
      ]);
      \Drupal::logger('bioland')->error('Error configuring system_pages term @tid: @message', [
        '@tid' => $term_data['tid'],
        '@message' => $e->getMessage(),
      ]);
    }
  }
  
  $summary = t('System pages search terms: @created created, @updated updated, @errors errors.', [
    '@created' => $created,
    '@updated' => $updated,
    '@errors' => $errors,
  ]);
  
  if (!empty($messages)) {
    \Drupal::logger('bioland')->notice($summary . ' ' . implode(' ', $messages));
  }
  
  return $summary;
}

/**
 * Helper function to configure content type weights for /node/add page ordering.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_content_type_weights() {
  $messages = [];
  $config_factory = \Drupal::configFactory();

  // Define content type weights (lower = appears first on /node/add)
  $weights = [
    'content' => -10,  // Site Content - appears first
    'forum' => 0,      // Forum topic - appears second
  ];

  foreach ($weights as $type => $weight) {
    $config = $config_factory->getEditable('node.type.' . $type);
    
    if (!$config->isNew()) {
      $config->set('weight', $weight);
      $config->save(TRUE);
      $messages[] = t('Set weight @weight for @type content type.', [
        '@weight' => $weight,
        '@type' => $type,
      ]);
    }
    else {
      $messages[] = t('Content type @type not found, skipping weight configuration.', [
        '@type' => $type,
      ]);
    }
  }

  if (empty($messages)) {
    return t('No content type weights configured.');
  }

  return implode("\n", $messages);
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Configure available menus for content type.
 */
function bioland_update_9028() {
  return _bioland_configure_content_type_available_menus();
}

/**
 * Configure content type taxonomy terms.
 */
function bioland_update_9029() {
  return _bioland_configure_content_types();
}

/**
 * Add system_pages search terms: BCH Search and ABSCH Search.
 */
function bioland_update_9035() {
  return _bioland_configure_system_pages_search_terms();
}

/**
 * Configure content type taxonomy term status based on site type (BL2 vs Biosafety).
 */
function bioland_update_9039() {
  return _bioland_configure_content_type_status_by_site_type();
}

/**
 * Configure content type weights for /node/add page ordering.
 */
function bioland_update_9041() {
  return _bioland_configure_content_type_weights();
}
