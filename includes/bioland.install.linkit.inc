<?php

/**
 * @file
 * Linkit configuration related install and update functions for Bioland module.
 *
 * This file contains helper functions and update hooks for configuring
 * Linkit profiles, matchers, editor integration, and field widgets.
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS
 * =============================================================================
 */

/**
 * Get Linkit matcher definitions.
 *
 * @return array
 *   Array of matcher definitions.
 */
function _bioland_get_linkit_matchers() {
  return [
    [
      'id' => 'entity:node',
      'settings' => [
        'metadata' => '[language:prefix][node:url:path]',
        'bundles' => ['content' => 'content'],
        'group_by_bundle' => TRUE,
        'substitution_type' => 'canonical',
        'limit' => 20,
        'include_unpublished' => TRUE,
      ],
      'weight' => -10,
    ],
    [
      'id' => 'entity:taxonomy_term',
      'settings' => [
        'metadata' => '[language:prefix][term:url:path]',
        'bundles' => ['system_pages' => 'system_pages', 'tags' => 'tags'],
        'group_by_bundle' => TRUE,
        'substitution_type' => 'canonical',
        'limit' => 20,
      ],
      'weight' => -9,
    ],
    [
      'id' => 'entity:media',
      'settings' => [
        'metadata' => '[language:prefix][media:url:path]',
        'bundles' => ['document' => 'document', 'image' => 'image', 'remote_video' => 'remote_video'],
        'group_by_bundle' => TRUE,
        'substitution_type' => 'canonical',
        'limit' => 20,
      ],
      'weight' => -8,
    ],
    [
      'id' => 'external',
      'settings' => [],
      'weight' => -7,
    ],
    [
      'id' => 'email',
      'settings' => [],
      'weight' => -6,
    ],
    [
      'id' => 'entity:comment',
      'settings' => [
        'metadata' => '[language:prefix][comment:url:path]',
        'bundles' => ['comment' => 'comment', 'comment_media' => 'comment_media', 'comment_taxonomy' => 'comment_taxonomy'],
        'group_by_bundle' => FALSE,
        'substitution_type' => 'canonical',
        'limit' => 20,
      ],
      'weight' => 0,
    ],
  ];
}

/**
 * Helper function to configure the default Linkit profile.
 *
 * Creates or updates the default Linkit profile with matchers for:
 * - entity:node (content bundle)
 * - entity:taxonomy_term (system_pages, tags bundles)
 * - entity:media (document, image, remote_video bundles)
 * - external links
 * - email links
 * - entity:comment (comment, comment_media, comment_taxonomy bundles)
 *
 * @return array
 *   Status array with 'status' key ('created', 'updated', or 'error') and optional 'message'.
 */
function _bioland_configure_linkit_profile() {
  if ($skip = _bioland_require_module('linkit', 'Linkit profile configuration')) {
    return ['status' => 'error', 'message' => $skip];
  }

  $config_factory = \Drupal::configFactory();
  $linkit_config = $config_factory->getEditable('linkit.linkit_profile.default');

  // Build matchers from data-driven definition.
  $matchers_config = [];
  foreach (_bioland_get_linkit_matchers() as $matcher) {
    $uuid = \Drupal::service('uuid')->generate();
    $matchers_config[$uuid] = [
      'id' => $matcher['id'],
      'uuid' => $uuid,
      'settings' => $matcher['settings'],
      'weight' => $matcher['weight'],
    ];
  }

  // Check if profile already exists.
  $existing_uuid = $linkit_config->get('uuid');
  $status = 'updated';

  if (empty($existing_uuid)) {
    // Create new profile.
    $status = 'created';
    $linkit_config
      ->set('uuid', \Drupal::service('uuid')->generate())
      ->set('langcode', 'en')
      ->set('status', TRUE)
      ->set('dependencies', ['module' => ['node', 'taxonomy']])
      ->set('label', 'Default')
      ->set('id', 'default')
      ->set('description', 'A default Linkit profile');
  }

  // Set the matchers configuration.
  $linkit_config->set('matchers', $matchers_config);
  $linkit_config->save(TRUE);

  // Configure the editor and filter for Linkit integration.
  _bioland_configure_linkit_editor();

  \Drupal::logger('bioland')->notice('Linkit default profile @status with Bioland matchers configuration.', [
    '@status' => $status,
  ]);

  return ['status' => $status];
}

/**
 * Configure the Full HTML editor and filter to use Linkit.
 *
 * Updates:
 * - editor.editor.full_html: Enables linkit_extension with default profile
 * - filter.format.full_html: Adds linkit filter with title and media_substitution
 *
 * @return array
 *   Status array with 'editor' and 'filter' keys indicating what was configured.
 */
function _bioland_configure_linkit_editor() {
  $config_factory = \Drupal::configFactory();
  $result = ['editor' => FALSE, 'filter' => FALSE];

  // Configure editor.editor.full_html with Linkit extension.
  if (\Drupal::moduleHandler()->moduleExists('editor')) {
    $editor_config = $config_factory->getEditable('editor.editor.full_html');
    
    if ($editor_config->get('editor')) {
      // Get existing settings or create new ones.
      $settings = $editor_config->get('settings') ?: [];
      
      // Add or update the linkit_extension in plugins.
      if (!isset($settings['plugins'])) {
        $settings['plugins'] = [];
      }
      
      $settings['plugins']['linkit_extension'] = [
        'linkit_enabled' => TRUE,
        'linkit_profile' => 'default',
      ];
      
      $editor_config->set('settings', $settings);
      
      // Add linkit to dependencies if not already present.
      $dependencies = $editor_config->get('dependencies') ?: [];
      if (!isset($dependencies['module'])) {
        $dependencies['module'] = [];
      }
      if (!in_array('linkit', $dependencies['module'])) {
        $dependencies['module'][] = 'linkit';
        sort($dependencies['module']);
        $editor_config->set('dependencies', $dependencies);
      }
      
      $editor_config->save(TRUE);
      $result['editor'] = TRUE;
      
      \Drupal::logger('bioland')->notice('Configured editor.editor.full_html with Linkit extension.');
    }
    else {
      \Drupal::logger('bioland')->notice('Editor full_html not found; skipping Linkit editor configuration.');
    }
  }

  // Configure filter.format.full_html with Linkit filter.
  $filter_config = $config_factory->getEditable('filter.format.full_html');
  
  if ($filter_config->get('format')) {
    // Get existing filters or create new array.
    $filters = $filter_config->get('filters') ?: [];
    
    // Add or update the linkit filter.
    $filters['linkit'] = [
      'id' => 'linkit',
      'provider' => 'linkit',
      'status' => TRUE,
      'weight' => 0,
      'settings' => [
        'title' => TRUE,
        'media_substitution' => 'metadata',
      ],
    ];
    
    $filter_config->set('filters', $filters);
    
    // Add linkit to dependencies if not already present.
    $dependencies = $filter_config->get('dependencies') ?: [];
    if (!isset($dependencies['module'])) {
      $dependencies['module'] = [];
    }
    if (!in_array('linkit', $dependencies['module'])) {
      $dependencies['module'][] = 'linkit';
      sort($dependencies['module']);
      $filter_config->set('dependencies', $dependencies);
    }
    
    $filter_config->save(TRUE);
    $result['filter'] = TRUE;
    
    \Drupal::logger('bioland')->notice('Configured filter.format.full_html with Linkit filter.');
  }
  else {
    \Drupal::logger('bioland')->notice('Filter format full_html not found; skipping Linkit filter configuration.');
  }

  return $result;
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Configure URL field with Linkit widget and language selector visibility.
 */
function bioland_update_9013() {
  $messages = [];

  // Load the content node form display.
  $form_display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('node.content.default');

  if (!$form_display) {
    return t('Form display node.content.default not found; skipping configuration.');
  }

  $changed = FALSE;

  // 1. Configure field_url to use Linkit widget if Linkit module is enabled.
  if (\Drupal::moduleHandler()->moduleExists('linkit')) {
    $url_component = $form_display->getComponent('field_url');
    if ($url_component) {
      // Use linkit widget with autocomplete for internal/external URLs.
      $form_display->setComponent('field_url', [
        'type' => 'linkit',
        'weight' => $url_component['weight'] ?? 0,
        'settings' => [
          'linkit_profile' => 'default',
          'linkit_auto_link_text' => FALSE,
          'placeholder_url' => '',
          'placeholder_title' => '',
        ],
        'third_party_settings' => [],
      ]);
      $changed = TRUE;
      $messages[] = t('Configured field_url to use Linkit widget.');
    }
    else {
      $messages[] = t('field_url not found in form display; skipping Linkit configuration.');
    }
  }
  else {
    $messages[] = t('Linkit module not enabled; skipping field_url widget configuration.');
  }

  // 2. Show language selector in the meta/sidebar area.
  // The langcode field needs to be visible and in the 'meta' group.
  $langcode_component = $form_display->getComponent('langcode');

  // Set langcode to be visible in the advanced/meta sidebar.
  $form_display->setComponent('langcode', [
    'type' => 'language_select',
    'weight' => $langcode_component['weight'] ?? 5,
    'settings' => [
      'include_locked' => FALSE,
    ],
    'region' => 'content',
    'third_party_settings' => [],
  ]);
  $changed = TRUE;
  $messages[] = t('Language selector is now visible on the form.');

  // Try to add langcode to the meta group if field_group module is available.
  if (\Drupal::moduleHandler()->moduleExists('field_group')) {
    $third_party = $form_display->getThirdPartySettings('field_group');
    
    // Check if there's a meta or advanced group we can add langcode to.
    foreach (['group_meta', 'group_advanced', 'group_sidebar'] as $group_name) {
      if (isset($third_party[$group_name])) {
        $group = $third_party[$group_name];
        if (!in_array('langcode', $group['children'] ?? [])) {
          $group['children'][] = 'langcode';
          $form_display->setThirdPartySetting('field_group', $group_name, $group);
          $messages[] = t('Added langcode to @group.', ['@group' => $group_name]);
          break;
        }
      }
    }
  }

  if ($changed) {
    $form_display->save();
  }

  return implode(' ', $messages);
}

/**
 * Configure Linkit profile with Bioland matchers.
 */
function bioland_update_9018() {
  if ($skip = _bioland_require_module('linkit', 'profile configuration')) {
    return $skip;
  }

  $result = _bioland_configure_linkit_profile();
  
  if ($result['status'] === 'created') {
    return t('Created and configured default Linkit profile with all matchers.');
  }
  elseif ($result['status'] === 'updated') {
    return t('Updated existing default Linkit profile with Bioland matchers configuration.');
  }
  
  return t('Linkit profile configuration completed: @message', ['@message' => $result['message'] ?? 'No changes needed.']);
}

/**
 * Configure Content type fields: non-translatable, remove defaults, Linkit widget.
 *
 * 1. Set field_attachments, field_components, field_type_placement, field_tags as non-translatable.
 * 2. Remove default value (today's date) from field_published.
 * 3. Configure field_url to use Linkit widget with auto-populate link text enabled.
 *
 * @version 1.0.30
 */
function bioland_update_9019() {
  $messages = _bioland_configure_content_fields();
  return empty($messages) ? t('Content fields already configured.') : implode(' ', $messages);
}
