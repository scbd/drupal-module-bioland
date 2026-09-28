<?php

/**
 * @file
 * Views installation and configuration functions for the Bioland module.
 *
 * Contains helper functions and update hooks for installing, configuring,
 * and fixing views.view.* configurations including:
 * - User admin view (administerusersbyrole_people)
 * - Comment admin view
 * - Media library view
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS - Views Installation & Configuration
 * =============================================================================
 */

/**
 * Helper function to install custom user admin view.
 *
 * Modifies the views.view.user_admin_people configuration to show
 * email address as the first sortable column at /admin/people.
 *
 * @return string
 *   Status message.
 */
function _bioland_install_user_admin_view() {
  // Check if views module is enabled.
  if ($skip = _bioland_require_module('views', 'user admin view installation')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('views.view.administerusersbyrole_people');
  
  if ($config->isNew()) {
    return t('User admin people view (administerusersbyrole_people) not found.');
  }
  
  try {
    // Get existing fields.
    $fields = $config->get('display.default.display_options.fields') ?: [];
    
    // Add the mail field if it doesn't exist.
    if (!isset($fields['mail'])) {
      $fields['mail'] = [
        'id' => 'mail',
        'table' => 'users_field_data',
        'field' => 'mail',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'entity_type' => 'user',
        'entity_field' => 'mail',
        'plugin_id' => 'field',
        'label' => 'Email',
        'exclude' => FALSE,
        'type' => 'email_mailto',
        'alter' => [
          'alter_text' => FALSE,
          'text' => '',
          'make_link' => FALSE,
          'path' => '',
          'absolute' => FALSE,
          'external' => FALSE,
          'replace_spaces' => FALSE,
          'path_case' => 'none',
          'trim_whitespace' => FALSE,
          'alt' => '',
          'rel' => '',
          'link_class' => '',
          'prefix' => '',
          'suffix' => '',
          'target' => '',
          'nl2br' => FALSE,
          'max_length' => 0,
          'word_boundary' => TRUE,
          'ellipsis' => TRUE,
          'more_link' => FALSE,
          'more_link_text' => '',
          'more_link_path' => '',
          'strip_tags' => FALSE,
          'trim' => FALSE,
          'preserve_tags' => '',
          'html' => FALSE,
        ],
        'element_type' => '',
        'element_class' => '',
        'element_label_type' => '',
        'element_label_class' => '',
        'element_label_colon' => TRUE,
        'element_wrapper_type' => '',
        'element_wrapper_class' => '',
        'element_default_classes' => TRUE,
        'empty' => '',
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
      ];
    }
    else {
      // Update the existing mail field to be visible with label and use email formatter.
      $fields['mail']['exclude'] = FALSE;
      $fields['mail']['label'] = 'Email';
      $fields['mail']['element_label_colon'] = TRUE;
      $fields['mail']['type'] = 'email_mailto';
    }
    
    // Reorder fields to put email after bulk_form but before name.
    // Order: user_bulk_form, mail, name, status, roles_target_id, created, access, operations
    $reordered = [];
    if (isset($fields['user_bulk_form'])) {
      $reordered['user_bulk_form'] = $fields['user_bulk_form'];
      unset($fields['user_bulk_form']);
    }
    if (isset($fields['mail'])) {
      $reordered['mail'] = $fields['mail'];
      unset($fields['mail']);
    }
    // Add remaining fields in their original order.
    $reordered = array_merge($reordered, $fields);
    
    $config->set('display.default.display_options.fields', $reordered);
    
    // Also update the table style info to make mail sortable.
    $style_info = $config->get('display.default.display_options.style.options.info') ?: [];
    $style_info['mail'] = [
      'sortable' => TRUE,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => FALSE,
      'responsive' => '',
    ];
    $config->set('display.default.display_options.style.options.info', $style_info);
    
    // Add mail to columns.
    $columns = $config->get('display.default.display_options.style.options.columns') ?: [];
    $columns['mail'] = 'mail';
    $config->set('display.default.display_options.style.options.columns', $columns);
    
    $config->save();
    
    // Clear the views cache so the updated view is recognized.
    if (\Drupal::hasService('views.views_data')) {
      \Drupal::service('views.views_data')->clear();
    }
    
    // Clear local task cache so menu tabs are discovered.
    if (\Drupal::hasService('plugin.manager.menu.local_task')) {
      \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
    }
    
    \Drupal::logger('bioland')->notice('Updated user admin view with email as first sortable column.');
    return t('Added email column as first sortable column at /admin/people.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to update user admin view: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error updating user admin view: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Helper function to install custom comment admin view.
 *
 * Updates the views.view.comment configuration to remove exposed sort options.
 *
 * @return string
 *   Status message.
 */
function _bioland_install_comment_admin_view() {
  // Check if views and comment modules are enabled.
  if ($skip = _bioland_require_module('views', 'comment admin view installation')) {
    return $skip;
  }
  if ($skip = _bioland_require_module('comment', 'comment admin view installation')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('views.view.comment');
  
  if ($config->isNew()) {
    return t('View comment not found.');
  }
  
  try {
    $view_data = $config->getRawData();
    $display = &$view_data['display']['default']['display_options'];
    
    // Remove exposed sort options.
    if (isset($display['exposed_form']['options'])) {
      $display['exposed_form']['options']['expose_sort_order'] = FALSE;
    }
    
    if (isset($display['sorts'])) {
      foreach ($display['sorts'] as $sort_id => &$sort) {
        $sort['exposed'] = FALSE;
      }
    }
    
    $config->setData($view_data)->save();
    
    // Clear the views cache so the updated view is recognized.
    if (\Drupal::hasService('views.views_data')) {
      \Drupal::service('views.views_data')->clear();
    }
    
    // Clear local task cache so menu tabs are discovered.
    if (\Drupal::hasService('plugin.manager.menu.local_task')) {
      \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
    }
    
    \Drupal::logger('bioland')->notice('Updated comment view: removed exposed sort options.');
    return t('Updated comment view: removed exposed sort options at /admin/content/comment.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to update comment view: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error updating comment view: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Helper function to install custom media library view.
 *
 * Updates the views.view.media configuration to make columns sortable
 * and remove exposed sort dropdowns.
 *
 * @return string
 *   Status message.
 */
function _bioland_install_media_library_view() {
  // Check if views and media modules are enabled.
  if ($skip = _bioland_require_module('views', 'media library view installation')) {
    return $skip;
  }
  if ($skip = _bioland_require_module('media', 'media library view installation')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('views.view.media');
  
  if ($config->isNew()) {
    return t('View media not found.');
  }
  
  try {
    $view_data = $config->getRawData();
    $display = &$view_data['display']['default']['display_options'];
    
    // Fields to make sortable.
    $sortable_fields = ['name', 'bundle', 'status', 'changed', 'uid'];
    
    // Update table style info to enable sorting on column headers.
    if (isset($display['style']['options']['info'])) {
      foreach ($sortable_fields as $field_id) {
        if (isset($display['style']['options']['info'][$field_id])) {
          $display['style']['options']['info'][$field_id]['sortable'] = TRUE;
          if (!isset($display['style']['options']['info'][$field_id]['default_sort_order'])) {
            $display['style']['options']['info'][$field_id]['default_sort_order'] = 'asc';
          }
        }
      }
    }
    
    // Remove exposed "Sort by" and "Order" dropdowns.
    if (isset($display['exposed_form']['options'])) {
      $display['exposed_form']['options']['expose_sort_order'] = FALSE;
    }
    
    // Un-expose all sort criteria.
    if (isset($display['sorts'])) {
      foreach ($display['sorts'] as $sort_id => &$sort) {
        $sort['exposed'] = FALSE;
      }
    }
    
    $config->setData($view_data)->save();
    
    // Clear the views cache so the updated view is recognized.
    if (\Drupal::hasService('views.views_data')) {
      \Drupal::service('views.views_data')->clear();
    }
    
    // Clear local task cache so menu tabs are discovered.
    if (\Drupal::hasService('plugin.manager.menu.local_task')) {
      \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
    }
    
    \Drupal::logger('bioland')->notice('Updated media view: enabled column sorting and removed Sort by/Order dropdowns.');
    return t('Updated media view: enabled column sorting and removed Sort by/Order dropdowns.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to update media view: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error updating media view: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Helper function to clean up broken comment admin view configurations.
 *
 * Removes any broken 'entity_id' relationships and 'type' filters that may
 * have been added by previous updates.
 *
 * @return string
 *   Status message.
 */
function _bioland_fix_comment_admin_view_filter() {
  // Check if views and comment modules are enabled.
  if ($skip = _bioland_require_module('views', 'comment admin view fix')) {
    return $skip;
  }
  if ($skip = _bioland_require_module('comment', 'comment admin view fix')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('views.view.comment');
  
  if ($config->isNew()) {
    return t('View comment not found.');
  }
  
  try {
    $view_data = $config->getRawData();
    $display = &$view_data['display']['default']['display_options'];
    
    // Remove the broken 'entity_id' relationship if it exists.
    if (isset($display['relationships']['entity_id'])) {
      unset($display['relationships']['entity_id']);
    }
    
    // Remove the broken 'type' filter if it exists.
    if (isset($display['filters']['type'])) {
      unset($display['filters']['type']);
    }
    
    $config->setData($view_data)->save();
    
    // Clear the views cache so the updated view is recognized.
    if (\Drupal::hasService('views.views_data')) {
      \Drupal::service('views.views_data')->clear();
    }
    
    // Clear local task cache.
    if (\Drupal::hasService('plugin.manager.menu.local_task')) {
      \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
    }
    
    \Drupal::logger('bioland')->notice('Fixed comment view: removed broken relationships and filters.');
    return t('Fixed comment view: removed broken relationships and filters at /admin/content/comment.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to fix comment view: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error fixing comment view: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Helper function to fix the email field formatter.
 *
 * Updates the mail field to use email_mailto type for clickable links.
 *
 * @return string
 *   Status message.
 */
function _bioland_fix_user_admin_email_formatter() {
  // Check if views module is enabled.
  if ($skip = _bioland_require_module('views', 'user admin email formatter fix')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('views.view.administerusersbyrole_people');
  
  if ($config->isNew()) {
    return t('User admin people view (administerusersbyrole_people) not found.');
  }
  
  try {
    $fields = $config->get('display.default.display_options.fields') ?: [];
    
    if (isset($fields['mail'])) {
      // Set the type to email_mailto for clickable mailto links.
      $fields['mail']['type'] = 'email_mailto';
      $config->set('display.default.display_options.fields', $fields);
      $config->save();
      
      // Clear the views cache.
      if (\Drupal::hasService('views.views_data')) {
        \Drupal::service('views.views_data')->clear();
      }
      
      \Drupal::logger('bioland')->notice('Fixed email field formatter to use mailto links.');
      return t('Updated email field to use clickable mailto links at /admin/people.');
    }
    
    return t('Email field not found in user admin view; running full install.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to fix email formatter: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error fixing email formatter: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Removes the stray hand-added "bundle" field from the media_library view.
 *
 * The gt site had a "bundle" field added by hand to the media_library view's
 * widget and widget_table displays, which is what renders the stray
 * "Document" link on every card in the "Add or select media" picker.
 * Idempotent: a no-op when the field is already absent from both displays.
 *
 * @return string
 *   Status message.
 */
function _bioland_remove_media_library_bundle_field() {
  if ($skip = _bioland_require_module('media_library', 'media library bundle field removal')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('views.view.media_library');

  if ($config->isNew()) {
    return t('View media_library not found; skipped bundle field removal.');
  }

  try {
    $removed = [];
    foreach (['widget', 'widget_table'] as $display_id) {
      $fields = $config->get('display.' . $display_id . '.display_options.fields');
      if (is_array($fields) && array_key_exists('bundle', $fields)) {
        unset($fields['bundle']);
        $config->set('display.' . $display_id . '.display_options.fields', $fields);
        $removed[] = $display_id;
      }
    }

    if (empty($removed)) {
      return t('media_library view widget displays already have no bundle field.');
    }

    $config->save();

    // Clear the views cache so the updated view is recognized.
    if (\Drupal::hasService('views.views_data')) {
      \Drupal::service('views.views_data')->clear();
    }

    \Drupal::logger('bioland')->notice('Removed stray bundle field from media_library view: @displays.', [
      '@displays' => implode(', ', $removed),
    ]);
    return t('Removed stray bundle field from media_library view: @displays.', [
      '@displays' => implode(', ', $removed),
    ]);
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to remove bundle field from media_library view: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error removing bundle field from media_library view: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Fixes the media.document media_library view display (BL-1205).
 *
 * Blank grey document cards in the "Add or select media" picker happen
 * because the media_library view display for the document bundle shows only
 * the (currently unstyled) thumbnail field. This makes the display show the
 * document's own image field and name instead, and hides the thumbnail.
 * Idempotent: a no-op once the display already has the desired shape.
 *
 * @return string
 *   Status message.
 */
function _bioland_fix_document_media_library_display() {
  if ($skip = _bioland_require_module('media', 'document media library display fix')) {
    return $skip;
  }

  $config = \Drupal::configFactory()->getEditable('core.entity_view_display.media.document.media_library');

  if ($config->isNew()) {
    return t('core.entity_view_display.media.document.media_library not found; skipped document card fix.');
  }

  try {
    $content = $config->get('content') ?: [];
    $hidden = $config->get('hidden') ?: [];

    $desired_image = [
      'type' => 'image',
      'label' => 'hidden',
      'settings' => [
        'image_style' => 'medium',
        'image_link' => '',
        'image_loading' => ['attribute' => 'lazy'],
      ],
      'third_party_settings' => [],
      'weight' => 0,
      'region' => 'content',
    ];
    $desired_name = [
      'type' => 'string',
      'label' => 'hidden',
      'settings' => [],
      'third_party_settings' => [],
      'weight' => 1,
      'region' => 'content',
    ];

    $changed = FALSE;

    if (($content['field_media_image'] ?? NULL) !== $desired_image) {
      $content['field_media_image'] = $desired_image;
      $changed = TRUE;
    }
    if (($content['name'] ?? NULL) !== $desired_name) {
      $content['name'] = $desired_name;
      $changed = TRUE;
    }
    if (isset($content['thumbnail'])) {
      unset($content['thumbnail']);
      $hidden['thumbnail'] = TRUE;
      $changed = TRUE;
    }
    elseif (($hidden['thumbnail'] ?? NULL) !== TRUE) {
      $hidden['thumbnail'] = TRUE;
      $changed = TRUE;
    }

    if (!$changed) {
      return t('Document media_library display already shows the image and name; left untouched.');
    }

    $config->set('content', $content);
    $config->set('hidden', $hidden);
    $config->save();

    \Drupal::logger('bioland')->notice('Fixed document media_library display: shows field_media_image + name, hides thumbnail.');
    return t('Fixed document media_library display: shows field_media_image + name, hides thumbnail.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Failed to fix document media_library display: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error fixing document media_library display: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Restricts the CKEditor "Insert media" picker to image + remote_video.
 *
 * Removes the Document tab from the CKEditor media picker on full_html and
 * basic_html by restricting the media_embed filter's allowed_media_types.
 * Idempotent: skips a format that does not exist or has no media_embed
 * filter, and does nothing once a format is already restricted.
 *
 * @return string
 *   Status message.
 */
function _bioland_restrict_media_embed_allowed_types() {
  $messages = [];
  $allowed = ['image' => 'image', 'remote_video' => 'remote_video'];
  $changed_formats = [];

  foreach (['full_html', 'basic_html'] as $format_id) {
    $config = \Drupal::configFactory()->getEditable('filter.format.' . $format_id);

    if ($config->isNew()) {
      $messages[] = t('filter.format.@format not found; skipped media_embed restriction.', ['@format' => $format_id]);
      continue;
    }

    $filters = $config->get('filters') ?: [];
    if (!isset($filters['media_embed'])) {
      $messages[] = t('@format has no media_embed filter; skipped media_embed restriction.', ['@format' => $format_id]);
      continue;
    }

    $settings = $filters['media_embed']['settings'] ?? [];
    if (($settings['allowed_media_types'] ?? NULL) === $allowed) {
      $messages[] = t('@format media_embed allowed types already restricted to image + remote_video.', ['@format' => $format_id]);
      continue;
    }

    $settings['allowed_media_types'] = $allowed;
    $filters['media_embed']['settings'] = $settings;
    $config->set('filters', $filters);
    $config->save();
    $changed_formats[] = $format_id;
    $messages[] = t('Restricted @format media_embed allowed types to image + remote_video.', ['@format' => $format_id]);
  }

  if (!empty($changed_formats)) {
    // Clear the editor plugin and filter caches so the Document tab
    // actually disappears from the CKEditor media picker.
    if (\Drupal::hasService('plugin.manager.filter')) {
      \Drupal::service('plugin.manager.filter')->clearCachedDefinitions();
    }
    if (\Drupal::hasService('plugin.manager.ckeditor5.plugin')) {
      \Drupal::service('plugin.manager.ckeditor5.plugin')->clearCachedDefinitions();
    }
    \Drupal::logger('bioland')->notice('Restricted media_embed allowed media types on: @formats.', [
      '@formats' => implode(', ', $changed_formats),
    ]);
  }

  return implode(' ', array_map('strval', $messages));
}

/*
 * =============================================================================
 * UPDATE HOOKS - Views Installation & Configuration
 * =============================================================================
 */

/**
 * Install custom user admin view with email as first sortable column.
 */
function bioland_update_9042() {
  return _bioland_install_user_admin_view();
}

/**
 * Install custom comment admin view with topic filter.
 */
function bioland_update_9043() {
  return _bioland_install_comment_admin_view();
}

/**
 * Install custom media library view with sortable columns.
 */
function bioland_update_9044() {
  return _bioland_install_media_library_view();
}

/**
 * Clean up broken comment admin view configuration.
 *
 * Removes broken relationships and filters that may have been added
 * by previous updates.
 */
function bioland_update_9049() {
  return _bioland_fix_comment_admin_view_filter();
}

/**
 * Fix email field formatter in user admin view to use clickable mailto links.
 */
function bioland_update_9050() {
  return _bioland_fix_user_admin_email_formatter();
}

/**
 * Clean up document media cards in the media library picker (BL-1205).
 *
 * Document media in the "Add or select media" picker rendered blank grey
 * cards with no name and a stray "Document" link, because a "bundle" field
 * had been added by hand to the media_library view's widget displays and
 * the media.document media_library view display only showed the (unstyled)
 * thumbnail. This also restricts the CKEditor "Insert media" picker to
 * image + remote_video so it no longer offers a Document tab.
 *
 * This is the highest-numbered hook at the time of writing, so per
 * SearchApiConvergenceHookTest it must also re-apply the canonical v2
 * Search API config; SearchApiConvergenceHookTest enforces that whichever
 * hook is highest re-converges that config.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 * @see _bioland_remove_media_library_bundle_field()
 * @see _bioland_fix_document_media_library_display()
 * @see _bioland_restrict_media_embed_allowed_types()
 */
function bioland_update_9094() {
  $messages = [];
  $messages[] = _bioland_remove_media_library_bundle_field();
  $messages[] = _bioland_fix_document_media_library_display();
  $messages[] = _bioland_restrict_media_embed_allowed_types();
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
