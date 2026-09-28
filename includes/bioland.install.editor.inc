<?php

/**
 * @file
 * Text Format & Editor related install functions for the Bioland module.
 *
 * Contains helper functions and update hooks for:
 * - Setting text fields to use full_html only format
 * - Updating existing content to full_html format
 * - Configuring full_html format with Linkit integration
 * - Configuring the CKEditor5 toolbar
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Canonical CKEditor5 inline image upload size limit for full_html (BL-1202).
 *
 * Shared by _bioland_configure_full_html_editor_toolbar() (the write) and its
 * own post-save verification, so the two can never drift out of sync.
 */
const BIOLAND_EDITOR_IMAGE_MAX_SIZE = '10 MB';

/**
 * Media types selectable in the CKEditor "Insert Media" dialog (BL-1206).
 *
 * Mirrors the BL-1205 allowlist in
 * _bioland_restrict_media_embed_allowed_types(): image + remote_video only.
 * document and hero are intentionally absent.
 */
const BIOLAND_MEDIA_EMBED_ALLOWED_TYPES = ['image', 'remote_video'];

/*
 * =============================================================================
 * HELPER FUNCTIONS
 * =============================================================================
 */

/**
 * Set text field format settings to only allow 'full_html' format.
 *
 * Updates field configuration for body (node), field_description (media),
 * and comment_body (comment) to restrict allowed text formats to only 'full_html'.
 *
 * @return array
 *   Array of status messages about the configuration updates.
 */
function _bioland_set_full_html_only_format() {
  $messages = [];
  $updated_count = 0;

  // Define field configurations to update.
  $fields_to_update = [
    'node' => [
      'body' => [],
    ],
    'media' => [
      'field_description' => [],
    ],
    'comment' => [
      'comment_body' => [],
    ],
  ];

  $entity_type_manager = \Drupal::entityTypeManager();

  foreach ($fields_to_update as $entity_type => $fields) {
    foreach ($fields as $field_name => $bundles) {
      // If no bundles specified, get all bundles that have this field.
      if (empty($bundles)) {
        $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
        if (!$field_storage) {
          $messages[] = "Field storage {$entity_type}.{$field_name} does not exist.";
          continue;
        }

        // Find all bundles using this field.
        $field_configs = $entity_type_manager
          ->getStorage('field_config')
          ->loadByProperties([
            'entity_type' => $entity_type,
            'field_name' => $field_name,
          ]);

        foreach ($field_configs as $field_config) {
          $bundle = $field_config->getTargetBundle();
          $bundles[] = $bundle;
        }
      }

      // Update each bundle's field configuration.
      foreach ($bundles as $bundle) {
        $field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
        if (!$field) {
          continue;
        }

        $settings = $field->getSettings();
        
        // Set allowed_formats to only include 'full_html'.
        $settings['allowed_formats'] = ['full_html'];
        $field->setSettings($settings);
        $field->save();
        $updated_count++;
        
        $messages[] = "Set {$entity_type}.{$bundle}.{$field_name} to only allow full_html format.";
      }
    }
  }

  if ($updated_count > 0) {
    \Drupal::logger('bioland')->notice('Updated @count field configuration(s) to only allow full_html format.', [
      '@count' => $updated_count,
    ]);
  }

  return $messages;
}

/**
 * Helper function to update existing content to use 'full_html' format.
 *
 * Updates body (node), field_description (media), and comment_body (comment)
 * field values to use 'full_html' format.
 *
 * @return array
 *   An array with counts of updated and errored entities.
 */
function _bioland_update_content_to_full_html() {
  $updated = 0;
  $errors = 0;
  $messages = [];
  $database = \Drupal::database();

  \Drupal::logger('bioland')->notice('Starting full_html update using DIRECT DATABASE QUERIES (no entity saves).');

  // Use direct database queries to update text format without triggering
  // entity saves, which can cause issues with translations that have NULL titles.

  // Update node body field format.
  try {
    // Update node__body table (current data).
    $node_updated = $database->update('node__body')
      ->fields(['body_format' => 'full_html'])
      ->condition('body_format', 'full_html', '!=')
      ->execute();

    // Update node_revision__body table (revision data).
    $node_rev_updated = $database->update('node_revision__body')
      ->fields(['body_format' => 'full_html'])
      ->condition('body_format', 'full_html', '!=')
      ->execute();

    if ($node_updated > 0 || $node_rev_updated > 0) {
      $updated += $node_updated;
      $messages[] = t('Updated @count node body fields (@rev revisions).', [
        '@count' => $node_updated,
        '@rev' => $node_rev_updated,
      ]);
    }
  }
  catch (\Exception $e) {
    $errors++;
    \Drupal::logger('bioland')->error('Error updating node body format: @message', [
      '@message' => $e->getMessage(),
    ]);
  }

  // Update media field_description format.
  if (\Drupal::moduleHandler()->moduleExists('media')) {
    try {
      // Check if table exists.
      if ($database->schema()->tableExists('media__field_description')) {
        $media_updated = $database->update('media__field_description')
          ->fields(['field_description_format' => 'full_html'])
          ->condition('field_description_format', 'full_html', '!=')
          ->execute();

        if ($media_updated > 0) {
          $updated += $media_updated;
          $messages[] = t('Updated @count media description fields.', ['@count' => $media_updated]);
        }
      }

      // Update revision table if exists.
      if ($database->schema()->tableExists('media_revision__field_description')) {
        $database->update('media_revision__field_description')
          ->fields(['field_description_format' => 'full_html'])
          ->condition('field_description_format', 'full_html', '!=')
          ->execute();
      }
    }
    catch (\Exception $e) {
      $errors++;
      \Drupal::logger('bioland')->error('Error updating media description format: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  // Update comment_body format.
  if (\Drupal::moduleHandler()->moduleExists('comment')) {
    try {
      // Check if table exists.
      if ($database->schema()->tableExists('comment__comment_body')) {
        $comment_updated = $database->update('comment__comment_body')
          ->fields(['comment_body_format' => 'full_html'])
          ->condition('comment_body_format', 'full_html', '!=')
          ->execute();

        if ($comment_updated > 0) {
          $updated += $comment_updated;
          $messages[] = t('Updated @count comment body fields.', ['@count' => $comment_updated]);
        }
      }
    }
    catch (\Exception $e) {
      $errors++;
      \Drupal::logger('bioland')->error('Error updating comment body format: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  // Clear entity caches so entities reflect the database changes.
  \Drupal::entityTypeManager()->getStorage('node')->resetCache();
  if (\Drupal::moduleHandler()->moduleExists('media')) {
    \Drupal::entityTypeManager()->getStorage('media')->resetCache();
  }
  if (\Drupal::moduleHandler()->moduleExists('comment')) {
    \Drupal::entityTypeManager()->getStorage('comment')->resetCache();
  }

  $summary = t('Updated @updated text format fields to full_html. Errors: @errors.', [
    '@updated' => $updated,
    '@errors' => $errors,
  ]);

  if (!empty($messages)) {
    \Drupal::logger('bioland')->notice($summary . ' ' . implode(' ', $messages));
  }

  return [
    'updated' => $updated,
    'errors' => $errors,
  ];
}

/**
 * Helper function to configure full_html text format.
 *
 * - Adds Linkit extension to editor.editor.full_html
 * - Removes the dead settings.plugins.ckeditor5_imageUpload.max_size key
 *   (core never reads it; see _bioland_configure_full_html_editor_toolbar()
 *   for the actual image upload limit, at the top-level image_upload key)
 * - Adds Linkit filter to filter.format.full_html
 * - Grants scbd_staff access to full_html format
 * - Disables basic_html editor
 *
 * @return string
 *   Status messages about configuration.
 */
function _bioland_configure_full_html_format() {
  if ($skip = _bioland_require_module('linkit', 'full_html configuration')) {
    return $skip;
  }

  $messages = [];
  $config_factory = \Drupal::configFactory();

  // 1. Configure editor.editor.full_html - Add Linkit extension and update image upload.
  $editor_config = $config_factory->getEditable('editor.editor.full_html');
  if ($editor_config && !$editor_config->isNew()) {
    $settings = $editor_config->get('settings') ?: [];

    // Add linkit_extension to plugins.
    if (!isset($settings['plugins']['linkit_extension'])) {
      $settings['plugins']['linkit_extension'] = [
        'linkit_enabled' => TRUE,
        'linkit_profile' => 'default',
      ];
      $messages[] = t('Added Linkit extension to full_html editor.');
    }

    // Remove the dead ckeditor5_imageUpload plugin key (BL-1202). Core never
    // reads settings.plugins.ckeditor5_imageUpload.max_size — the effective
    // limit lives at the top-level image_upload.max_size key, written by
    // _bioland_configure_full_html_editor_toolbar(). Unsetting here (rather
    // than simply no longer writing it) also clears it from already-installed
    // sites, where it can trip config validation on Drupal 10.3+/11.
    if (isset($settings['plugins']['ckeditor5_imageUpload'])) {
      unset($settings['plugins']['ckeditor5_imageUpload']);
      $messages[] = t('Removed dead ckeditor5_imageUpload plugin key from full_html editor settings.');
    }

    $editor_config->set('settings', $settings);
    $editor_config->save(TRUE);
  }
  else {
    $messages[] = t('editor.editor.full_html not found; skipping editor configuration.');
  }

  // 2. Configure filter.format.full_html - Add Linkit filter and dependencies.
  $filter_config = $config_factory->getEditable('filter.format.full_html');
  if ($filter_config && !$filter_config->isNew()) {
    // Add linkit to module dependencies.
    $dependencies = $filter_config->get('dependencies') ?: [];
    $modules = $dependencies['module'] ?? [];
    
    $required_modules = ['editor', 'linkit', 'media'];
    $changed = FALSE;
    foreach ($required_modules as $module) {
      if (!in_array($module, $modules)) {
        $modules[] = $module;
        $changed = TRUE;
      }
    }
    
    if ($changed) {
      sort($modules);
      $dependencies['module'] = array_values($modules);
      $filter_config->set('dependencies', $dependencies);
      $messages[] = t('Added linkit and media to full_html dependencies.');
    }

    // Add linkit filter.
    $filters = $filter_config->get('filters') ?: [];
    if (!isset($filters['linkit'])) {
      $filters['linkit'] = [
        'id' => 'linkit',
        'provider' => 'linkit',
        'status' => TRUE,
        'weight' => 0,
        'settings' => [
          'title' => TRUE,
        ],
      ];
      $filter_config->set('filters', $filters);
      $messages[] = t('Added Linkit filter to full_html format.');
    }

    // Add scbd_staff role permission.
    $roles = $filter_config->get('roles') ?: [];
    if (!in_array('scbd_staff', $roles)) {
      $roles[] = 'scbd_staff';
      $filter_config->set('roles', $roles);
      $messages[] = t('Granted scbd_staff access to full_html format.');
    }

    $filter_config->save(TRUE);
  }
  else {
    $messages[] = t('filter.format.full_html not found; skipping filter configuration.');
  }

  // 3. Also grant permission via role entity for use text format.
  $scbd_staff = \Drupal\user\Entity\Role::load('scbd_staff');
  if ($scbd_staff) {
    if (!$scbd_staff->hasPermission('use text format full_html')) {
      $scbd_staff->grantPermission('use text format full_html');
      $scbd_staff->save();
      $messages[] = t('Granted "use text format full_html" permission to scbd_staff role.');
    }
  }

  // 4. Disable basic_html editor.
  $basic_editor = $config_factory->getEditable('editor.editor.basic_html');
  if ($basic_editor && !$basic_editor->isNew()) {
    $status = $basic_editor->get('status');
    if ($status !== FALSE) {
      $basic_editor->set('status', FALSE);
      $basic_editor->save();
      $messages[] = t('Disabled basic_html editor.');
    }
  }
  else {
    $messages[] = t('editor.editor.basic_html not found; skipping disable.');
  }

  if (empty($messages)) {
    return t('full_html format already configured.');
  }

  \Drupal::logger('bioland')->notice('full_html configuration: @messages', [
    '@messages' => implode(' ', $messages),
  ]);

  return implode(' ', $messages);
}

/**
 * Helper function to configure full_html editor toolbar and image upload limit.
 *
 * - Sets image upload max size to BIOLAND_EDITOR_IMAGE_MAX_SIZE (10 MB)
 * - Ensures image upload is enabled (image_upload.status)
 * - Updates CKEditor5 toolbar with comprehensive button configuration
 *
 * @return string
 *   Status messages about configuration.
 */
function _bioland_configure_full_html_editor_toolbar() {
  $messages = [];
  $config_factory = \Drupal::configFactory();

  $editor_config = $config_factory->getEditable('editor.editor.full_html');
  if (!$editor_config || $editor_config->isNew()) {
    return t('editor.editor.full_html not found; skipping editor configuration.');
  }

  try {
    // Get current values for debugging
    $current_max_size = $editor_config->get('image_upload.max_size');
    $current_toolbar = $editor_config->get('settings.toolbar.items');

    // Update image upload max size to the canonical limit.
    $editor_config->set('image_upload.max_size', BIOLAND_EDITOR_IMAGE_MAX_SIZE);
    $messages[] = t('Set image upload max size to @size (was: @old).', [
      '@size' => BIOLAND_EDITOR_IMAGE_MAX_SIZE,
      '@old' => $current_max_size ?: 'not set',
    ]);

    // Ensure image upload is enabled, but only default it on when the key
    // is missing entirely; never override an admin's intentional FALSE.
    $image_upload_settings = $editor_config->get('image_upload') ?? [];
    if (!array_key_exists('status', $image_upload_settings)) {
      $editor_config->set('image_upload.status', TRUE);
      $messages[] = t('Enabled image upload for full_html editor.');
    }

    // Define the new toolbar items.
    $toolbar_items = [
      'bold',
      'underline',
      'italic',
      'link',
      'icon',
      '|',
      'numberedList',
      'bulletedList',
      '|',
      'alignment',
      'indent',
      'outdent',
      '|',
      'insertTable',
      'bootstrapGrid',
      '|',
      'drupalMedia',
      'drupalInsertImage',
      'heading',
      '|',
      'Fullscreen',
      'undo',
      'redo',
      'showBlocks',
      '|',
      'horizontalLine',
      'blockQuote',
      'specialCharacters',
      'code',
      'codeBlock',
      '|',
      'strikethrough',
      'superscript',
      'subscript',
      'removeFormat',
      '|',
      'sourceEditing'
    ];

    $editor_config->set('settings.toolbar.items', $toolbar_items);
    $messages[] = t('Updated full_html editor toolbar configuration with @count items (was: @old).', [
      '@count' => count($toolbar_items),
      '@old' => is_array($current_toolbar) ? count($current_toolbar) : 'not set',
    ]);

    // Save with TRUE to rebuild cache
    $editor_config->save(TRUE);
    
    // Clear render cache and plugin cache to ensure changes take effect
    \Drupal::service('plugin.manager.ckeditor5.plugin')->clearCachedDefinitions();
    \Drupal::cache('render')->invalidateAll();
    
    // Verify the save worked by reloading
    $editor_config_verify = $config_factory->get('editor.editor.full_html');
    $saved_max_size = $editor_config_verify->get('image_upload.max_size');
    $saved_toolbar = $editor_config_verify->get('settings.toolbar.items');
    
    if ($saved_max_size === BIOLAND_EDITOR_IMAGE_MAX_SIZE && is_array($saved_toolbar) && count($saved_toolbar) === count($toolbar_items)) {
      $messages[] = t('✓ Verified: Changes saved successfully.');
      \Drupal::logger('bioland')->notice('Updated editor.editor.full_html with new toolbar and @size image limit - VERIFIED.', [
        '@size' => BIOLAND_EDITOR_IMAGE_MAX_SIZE,
      ]);
    }
    else {
      $messages[] = t('⚠ Warning: Save verification failed. Max size: @size, Toolbar items: @items', [
        '@size' => $saved_max_size ?: 'not saved',
        '@items' => is_array($saved_toolbar) ? count($saved_toolbar) : 'not saved',
      ]);
      \Drupal::logger('bioland')->warning('editor.editor.full_html changes may not have persisted. Max size: @size, Toolbar: @toolbar', [
        '@size' => $saved_max_size,
        '@toolbar' => is_array($saved_toolbar) ? count($saved_toolbar) : 'null',
      ]);
    }
  }
  catch (\Exception $e) {
    $error_msg = t('Error saving editor configuration: @message', ['@message' => $e->getMessage()]);
    $messages[] = $error_msg;
    \Drupal::logger('bioland')->error('Failed to configure editor.editor.full_html: @message', [
      '@message' => $e->getMessage(),
    ]);
  }

  return implode("\n", $messages);
}

/**
 * Pure helper: build the media_embed allowed_media_types list (BL-1206).
 *
 * Only 'image' and 'remote_video' are selectable in the CKEditor "Insert
 * Media" dialog, the same allowlist BL-1205 established in
 * _bioland_restrict_media_embed_allowed_types(). 'document' (inserted /
 * linked, not embedded) and 'hero' (a page-level banner, never inline
 * body content) are deliberately excluded, as is any other type. The
 * allowlist is intersected with the media types that actually exist on
 * the site so a missing type is never written into config. Returns the
 * keyed shape MediaEmbed's checkboxes settings form stores in config:
 * id => id, stable sorted, empty in -> empty out.
 *
 * @param string[] $type_ids
 *   Media type machine names that exist on the site.
 *
 * @return array
 *   Allowed media type ids, keyed by id.
 */
function _bioland_media_embed_allowed_types(array $type_ids): array {
  $allowed = array_values(array_intersect(
    BIOLAND_MEDIA_EMBED_ALLOWED_TYPES,
    $type_ids
  ));
  sort($allowed, SORT_STRING);
  return array_combine($allowed, $allowed);
}

/**
 * Helper function to restrict media types selectable in the CKEditor
 * "Insert Media" (media_embed) dialog on full_html (BL-1206).
 *
 * Loads filter.format.full_html, ensures the media_embed filter exists and
 * is enabled, and sets settings.allowed_media_types to image + remote_video
 * (BIOLAND_MEDIA_EMBED_ALLOWED_TYPES, intersected with the media types that
 * exist on the site). allowed_view_modes is left untouched. Idempotent; skips with
 * a message when media / media_library is not enabled, or the format is
 * missing.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_media_embed_allowed_types() {
  if ($skip = _bioland_require_module('media', 'media_embed allowed types configuration')) {
    return $skip;
  }
  if ($skip = _bioland_require_module('media_library', 'media_embed allowed types configuration')) {
    return $skip;
  }

  $config_factory = \Drupal::configFactory();
  $filter_config = $config_factory->getEditable('filter.format.full_html');
  if (!$filter_config || $filter_config->isNew()) {
    return t('filter.format.full_html not found; skipping media_embed configuration.');
  }

  $media_type_ids = array_keys(\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple());
  $allowed_types = _bioland_media_embed_allowed_types($media_type_ids);

  $messages = [];
  $changed = FALSE;

  $filters = $filter_config->get('filters') ?: [];
  if (!isset($filters['media_embed'])) {
    $filters['media_embed'] = [
      'id' => 'media_embed',
      'provider' => 'media',
      'status' => TRUE,
      'weight' => 0,
      'settings' => [
        'default_view_mode' => 'default',
        'allowed_media_types' => $allowed_types,
        'allowed_view_modes' => [],
      ],
    ];
    $messages[] = t('Added media_embed filter to full_html format.');
    $changed = TRUE;
  }
  else {
    if (empty($filters['media_embed']['status'])) {
      $filters['media_embed']['status'] = TRUE;
      $messages[] = t('Enabled media_embed filter on full_html format.');
      $changed = TRUE;
    }

    $current_types = $filters['media_embed']['settings']['allowed_media_types'] ?? [];
    if ($current_types !== $allowed_types) {
      $filters['media_embed']['settings']['allowed_media_types'] = $allowed_types;
      $messages[] = t('Restricted full_html media_embed allowed types to image + remote_video.');
      $changed = TRUE;
    }
  }

  if ($changed) {
    $filter_config->set('filters', $filters);

    // Ensure the media module dependency is declared.
    $dependencies = $filter_config->get('dependencies') ?: [];
    $modules = $dependencies['module'] ?? [];
    if (!in_array('media', $modules, TRUE)) {
      $modules[] = 'media';
      sort($modules);
      $dependencies['module'] = array_values($modules);
      $filter_config->set('dependencies', $dependencies);
    }

    $filter_config->save(TRUE);
  }
  else {
    $messages[] = t('full_html media_embed allowed types already configured.');
  }

  \Drupal::logger('bioland')->notice('media_embed allowed types configuration: @messages', [
    '@messages' => implode(' ', $messages),
  ]);

  return implode(' ', $messages);
}

/**
 * Contrib modules Bioland enables when the codebase provides them (BL-917).
 *
 * All five ship in the drupal-docker-wrapper image from 11.4.7-v7.
 *
 * @return string[]
 *   Module machine names.
 */
function _bioland_contrib_feature_modules() {
  return [
    'toast_image_editor',
    'ckeditor_media_resizer',
    'llms_txt',
    'ckeditor5_fullscreen',
    'ckeditor5_icons',
  ];
}

/**
 * Enable the contrib feature modules that are present but not yet enabled.
 *
 * Already-enabled modules are left alone, so this is safe to run more than
 * once. A module missing from the codebase (an image older than
 * drupal-docker-wrapper 11.4.7-v7), or one whose install throws (for example
 * a missing dependency), is skipped, logged and reported rather than failing
 * the run. bioland_requirements() keeps flagging it on the status report
 * until someone enables it by hand.
 *
 * @return string
 *   Plain-text summary. Not t(): translation is not dependable mid-update.
 */
function _bioland_enable_contrib_feature_modules() {
  $module_handler = \Drupal::moduleHandler();
  // reset() first: the cached list can predate the image that added these
  // modules (updb before cache rebuild), which would skip them for good.
  $module_list = \Drupal::service('extension.list.module')->reset();
  $installer = \Drupal::service('module_installer');
  $logger = \Drupal::logger('bioland');

  $installed = [];
  $enabled = [];
  $missing = [];
  $failed = [];
  foreach (_bioland_contrib_feature_modules() as $module) {
    if ($module_handler->moduleExists($module)) {
      $enabled[] = $module;
      continue;
    }
    if (!$module_list->exists($module)) {
      $missing[] = $module;
      $logger->warning('Module @module is not in the codebase; skipped enabling it (needs drupal-docker-wrapper 11.4.7-v7 or higher).', ['@module' => $module]);
      continue;
    }
    // One module per call: install() resolves every listed module's
    // dependencies up front, so one bad module would block all of them.
    try {
      $installer->install([$module]);
      $installed[] = $module;
    }
    catch (\Exception $e) {
      $failed[] = $module . ' (' . $e->getMessage() . ')';
      $logger->warning('Could not enable @module: @message', ['@module' => $module, '@message' => $e->getMessage()]);
    }
  }

  $messages = [];
  if ($installed) {
    $messages[] = 'Enabled: ' . implode(', ', $installed) . '.';
  }
  if ($enabled) {
    $messages[] = 'Already enabled: ' . implode(', ', $enabled) . '.';
  }
  if ($missing) {
    $messages[] = 'Not in the codebase, skipped (needs drupal-docker-wrapper 11.4.7-v7 or higher): ' . implode(', ', $missing) . '.';
  }
  if ($failed) {
    $messages[] = 'Failed to enable, skipped: ' . implode('; ', $failed) . '.';
  }

  return implode(' ', $messages);
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Set field format settings to only allow 'full_html' for body, field_description, comment_body.
 *
 * Updates field configuration to restrict allowed text formats to only 'full_html'.
 */
function bioland_update_9016() {
  $messages = _bioland_set_full_html_only_format();
  
  if (!empty($messages)) {
    return t('Updated field configurations to only allow full_html format: @details', [
      '@details' => implode(' ', $messages),
    ]);
  }

  return t('No field configurations were updated.');
}

/**
 * Update existing content to use 'full_html' format for body, field_description, comment_body.
 *
 * Uses direct database queries to avoid entity save issues with translations
 * that have NULL titles.
 */
function bioland_update_9017(&$sandbox) {
  // This update now uses direct database queries via _bioland_update_content_to_full_html()
  // to avoid issues with translations that have NULL titles.
  // The entity-based approach was causing IntegrityConstraintViolation errors
  // when saving nodes with translations that were created without proper title values.
  
  if (!isset($sandbox['#finished'])) {
    $result = _bioland_update_content_to_full_html();
    $sandbox['#finished'] = 1;
    
    return t('Updated @updated text format fields to full_html using direct database queries. Errors: @errors.', [
      '@updated' => $result['updated'],
      '@errors' => $result['errors'],
    ]);
  }
  
  return t('Update already completed.');
}

/**
 * Configure full_html text format with Linkit integration and disable basic_html.
 *
 * @version 1.0.30
 */
function bioland_update_9022() {
  return _bioland_configure_full_html_format();
}

/**
 * Update all text format fields to use full_html.
 */
function bioland_update_9034() {
  _bioland_update_content_to_full_html();
  return t('Updated all text format fields to use full_html format.');
}

/**
 * Update full_html editor: max image size (BIOLAND_EDITOR_IMAGE_MAX_SIZE)
 * and new toolbar configuration.
 */
function bioland_update_9038() {
  return _bioland_configure_full_html_editor_toolbar();
}

/**
 * Raise the CKEditor inline image upload limit to 10 MB (BL-1202).
 *
 * Two prior hooks disagreed about the image upload limit for full_html:
 * bioland_update_9022() (via _bioland_configure_full_html_format()) wrote a
 * dead settings.plugins.ckeditor5_imageUpload.max_size = 250kb key that core
 * never reads, and bioland_update_9038() (via
 * _bioland_configure_full_html_editor_toolbar()) wrote the real limit,
 * image_upload.max_size, as 50kb. Re-running both helpers here converges
 * every site on the single canonical limit (BIOLAND_EDITOR_IMAGE_MAX_SIZE)
 * and clears the dead key.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every
 * site, so per SearchApiConvergenceHookTest it must also re-apply the
 * canonical v2 Search API config.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9093() {
  $messages = [];
  $messages[] = _bioland_configure_full_html_format();
  $messages[] = _bioland_configure_full_html_editor_toolbar();
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Enable the toast_image_editor, ckeditor_media_resizer, llms_txt and
 * CKEditor 5 contrib modules.
 *
 * BL-917. See _bioland_enable_contrib_feature_modules() for the skip rules.
 *
 * Numbered 9096 because the COP-17 integration branch (bsl-cop-17-dev)
 * already carries 9084-9095 from sibling PRs (BL-815 through BL-1206); the
 * original 9089 sat below those and never ran on a site already past them.
 * MERGE ORDER: land this after those siblings reach the same branch. Drupal
 * only runs hooks above a site's stored schema, so a site that records 9096
 * first would never run a later-merged 9084-9095.
 *
 * As the highest-numbered hook it is the last writer for every site, so per
 * SearchApiConvergenceHookTest it must also re-apply the canonical v2 Search
 * API config.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9096() {
  $messages = [];
  $messages[] = _bioland_enable_contrib_feature_modules();

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
