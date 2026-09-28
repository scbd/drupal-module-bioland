<?php

/**
 * @file
 * Form Display installation functions for Bioland module.
 *
 * Contains helper functions and update hooks related to entity form display
 * configuration, including langcode field placement and language settings.
 */

/**
 * Helper function to configure langcode field on content form display.
 *
 * Adds the langcode field to the content node form with label "Language",
 * placed in the sidebar (advanced group) and collapsed by default.
 * Also enables language_alterable setting for the content node type.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_langcode_form_display() {
  $messages = [];
  
  // First, enable language_alterable for content node type.
  $config_factory = \Drupal::configFactory();
  $language_config = $config_factory->getEditable('language.content_settings.node.content');
  
  if (!$language_config->isNew()) {
    $language_config->set('language_alterable', TRUE);
    $language_config->save(TRUE);
    $messages[] = 'Enabled language_alterable for content node type.';
    \Drupal::logger('bioland')->notice('Enabled language_alterable for content node type.');
  }
  
  $entity_form_display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('node.content.default');

  if (!$entity_form_display) {
    return t('Form display node.content.default not found; skipping langcode configuration.');
  }

  // Add langcode field to the form display.
  // Use the language_select widget which is the standard for langcode fields.
  $entity_form_display->setComponent('langcode', [
    'type' => 'language_select',
    'weight' => 11, // After field_order (weight 10)
    'region' => 'content',
    'settings' => [
      'include_locked' => TRUE,
    ],
    'third_party_settings' => [],
  ]);

  $entity_form_display->save();
  $messages[] = 'Added langcode field to content form display.';

  // Now configure the field_group if the module is available.
  // The langcode field should be in a collapsible group in the sidebar.
  if (\Drupal::moduleHandler()->moduleExists('field_group')) {
    $form_display_config = $config_factory->getEditable('core.entity_form_display.node.content.default');

    if (!$form_display_config->isNew()) {
      $third_party = $form_display_config->get('third_party_settings') ?: [];

      // Create a language group if it doesn't exist.
      if (!isset($third_party['field_group']['group_language'])) {
        $third_party['field_group']['group_language'] = [
          'label' => 'Language',
          'children' => ['langcode'],
          'parent_name' => '',
          'weight' => 99, // Near the end of sidebar
          'format_type' => 'details_sidebar',
          'format_settings' => [
            'classes' => '',
            'show_empty_fields' => FALSE,
            'id' => '',
            'open' => FALSE, // Collapsed by default
            'description' => '',
            'required_fields' => FALSE,
            'weight' => 0,
          ],
          'region' => 'content',
        ];

        $form_display_config->set('third_party_settings', $third_party);
        $form_display_config->save(TRUE);
        $messages[] = 'Added langcode field to content form in collapsible Language group.';
        \Drupal::logger('bioland')->notice('Added langcode field to content form in collapsible Language group.');
      }
      else {
        // Ensure langcode is in the group.
        $children = $third_party['field_group']['group_language']['children'] ?? [];
        if (!in_array('langcode', $children)) {
          $third_party['field_group']['group_language']['children'][] = 'langcode';
          $form_display_config->set('third_party_settings', $third_party);
          $form_display_config->save(TRUE);
          $messages[] = 'Added langcode to existing Language group.';
        }
        else {
          $messages[] = 'Langcode already in Language group.';
        }
      }
    }
  }
  else {
    $messages[] = 'Field_group module not available for sidebar placement.';
  }

  return implode(' ', $messages);
}

/**
 * Helper function to disable comment field on view displays.
 *
 * Removes the comment field from view displays for specified entity types
 * to prevent it from being rendered.
 *
 * @return string
 *   Status message.
 */
function _bioland_disable_comment_field_display() {
  $messages = [];
  
  // Define entity types and bundles where comment field should be hidden.
  $display_configs = [
    'node.content.default',
    'media.document.default',
    'media.hero.default',
    'media.image.default',
    'media.remote_video.default',
  ];
  
  $entity_view_display_storage = \Drupal::entityTypeManager()
    ->getStorage('entity_view_display');
  
  foreach ($display_configs as $display_id) {
    try {
      $display = $entity_view_display_storage->load($display_id);
      
      if (!$display) {
        $messages[] = t('View display @id not found; skipping.', ['@id' => $display_id]);
        continue;
      }
      
      // Remove the comment field if it exists in the display.
      if ($display->getComponent('comment')) {
        $display->removeComponent('comment');
        $display->save();
        $messages[] = t('Removed comment field from @id view display.', ['@id' => $display_id]);
        \Drupal::logger('bioland')->notice('Removed comment field from @id view display.', ['@id' => $display_id]);
      }
      else {
        $messages[] = t('Comment field already not displayed on @id.', ['@id' => $display_id]);
      }
    }
    catch (\Exception $e) {
      $messages[] = t('Error processing @id: @message', [
        '@id' => $display_id,
        '@message' => $e->getMessage(),
      ]);
      \Drupal::logger('bioland')->error('Error removing comment field from @id: @message', [
        '@id' => $display_id,
        '@message' => $e->getMessage(),
      ]);
    }
  }
  
  return implode(' ', $messages);
}

/**
 * Helper function to configure media view displays (BL-1206).
 *
 * - Remote video default display: drops the generic 'name' field, keeping
 *   only field_media_oembed_video.
 * - Document media_library display: drops the generic 'thumbnail' field and
 *   shows the media's own image field instead (found dynamically via
 *   field_config, never hard-coded), reusing the thumbnail's image style
 *   when one was set, else 'medium'.
 *
 * Tolerant of prior state from sibling ticket BL-1205 (thumbnail already
 * absent, image field already present) — idempotent either way.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_media_view_displays() {
  $messages = [];
  $entity_view_display_storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

  // Remote video: default display should not show the generic name field.
  $remote_video_display = $entity_view_display_storage->load('media.remote_video.default');
  if (!$remote_video_display) {
    $messages[] = t('View display core.entity_view_display.media.remote_video.default not found; skipping.');
  }
  else {
    if ($remote_video_display->getComponent('name')) {
      $remote_video_display->removeComponent('name');
      $remote_video_display->save();
      $messages[] = t('Removed name field from media.remote_video.default view display.');
    }
    else {
      $messages[] = t('media.remote_video.default view display already excludes the name field.');
    }
  }

  // Document media_library: swap the generic thumbnail for the media's own
  // image field.
  $document_display = $entity_view_display_storage->load('media.document.media_library');
  if (!$document_display) {
    $messages[] = t('View display core.entity_view_display.media.document.media_library not found; skipping.');
  }
  else {
    $image_fields = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->loadByProperties([
        'entity_type' => 'media',
        'bundle' => 'document',
        'field_type' => 'image',
      ]);

    if (empty($image_fields)) {
      \Drupal::logger('bioland')->notice('BL-1206: no image field found on media.document; skipping media_library display image swap.');
      $messages[] = t('No image field found on media.document; skipping media_library display image swap.');
    }
    else {
      // Choose deterministically: prefer field_media_image when present,
      // otherwise fall back to the alphabetically first field name so the
      // outcome does not depend on storage/load order.
      $image_fields_by_name = [];
      foreach ($image_fields as $image_field_candidate) {
        $image_fields_by_name[$image_field_candidate->getName()] = $image_field_candidate;
      }

      if (isset($image_fields_by_name['field_media_image'])) {
        $image_field_name = 'field_media_image';
      }
      else {
        $sorted_field_names = array_keys($image_fields_by_name);
        sort($sorted_field_names);
        $image_field_name = reset($sorted_field_names);
      }

      $image_field = $image_fields_by_name[$image_field_name];

      if (count($image_fields_by_name) > 1) {
        \Drupal::logger('bioland')->notice('BL-1206: media.document has @count image fields (@names); using @chosen for the media_library display.', [
          '@count' => count($image_fields_by_name),
          '@names' => implode(', ', array_keys($image_fields_by_name)),
          '@chosen' => $image_field_name,
        ]);
      }

      $changed = FALSE;

      // Capture the thumbnail's image style (if any) before removing it, so
      // the new image field component keeps the same visual size.
      $image_style = 'medium';
      $thumbnail_component = $document_display->getComponent('thumbnail');
      if (!empty($thumbnail_component['settings']['image_style'])) {
        $image_style = $thumbnail_component['settings']['image_style'];
      }

      if ($thumbnail_component) {
        $document_display->removeComponent('thumbnail');
        $changed = TRUE;
      }

      $target_component = [
        'type' => 'image',
        'label' => 'hidden',
        'weight' => 0,
        'settings' => [
          'image_style' => $image_style,
          'image_link' => '',
        ],
        'region' => 'content',
      ];
      $existing_image_component = $document_display->getComponent($image_field_name);
      $existing_matches = $existing_image_component
        && ($existing_image_component['type'] ?? NULL) === $target_component['type']
        && ($existing_image_component['label'] ?? NULL) === $target_component['label']
        && ($existing_image_component['settings']['image_style'] ?? NULL) === $target_component['settings']['image_style']
        && ($existing_image_component['settings']['image_link'] ?? '') === $target_component['settings']['image_link'];

      if (!$existing_matches) {
        $document_display->setComponent($image_field_name, $target_component);
        $changed = TRUE;
      }

      if ($changed) {
        $document_display->save();
        $messages[] = t('Configured @field as the image display on media.document.media_library (removed thumbnail).', ['@field' => $image_field_name]);
      }
      else {
        $messages[] = t('media.document.media_library view display already configured.');
      }
    }
  }

  return implode(' ', $messages);
}

/*
 * ===========================================================================
 * UPDATE HOOKS - Form Display
 * ===========================================================================
 */

/**
 * Configure langcode form display.
 *
 * @version 1.0.30
 */
function bioland_update_9027() {
  return _bioland_configure_langcode_form_display();
}

/**
 * Configure default language settings.
 *
 * Add langcode field to content form sidebar.
 */
function bioland_update_9036() {
  return _bioland_configure_langcode_form_display();
}

/**
 * Update langcode form widget settings.
 *
 * This update hook clears form caches to ensure form alterations take effect.
 * The menu_parent field will now be hidden when editing translations
 * of menu links (non-source language), ensuring menu structure is only
 * managed from the source language.
 */
function bioland_update_9053() {
  // Clear form caches to ensure the form alter takes effect.
  \Drupal::service('cache.render')->invalidateAll();
  
  return t('Menu parent field will now be hidden on menu link translation forms. Menu structure should be managed from the source language only.');
}

/**
 * Disable comment field on view displays.
 *
 * Removes comment field from view displays for node:content and media types
 * (document, hero, image, remote_video) to prevent comments from being rendered.
 */
function bioland_update_9054() {
  return _bioland_disable_comment_field_display();
}

/**
 * Configure timezone settings.
 *
 * Sets timezone.user.configurable to FALSE in system.date config to prevent
 * users from changing their timezone.
 */
function bioland_update_9055() {
  $date_config = \Drupal::configFactory()->getEditable('system.date');

  // Set user timezone configuration to FALSE
  $date_config->set('timezone.user.configurable', FALSE);
  $date_config->save();

  return t('Set timezone.user.configurable to FALSE in system.date configuration.');
}

/**
 * Media display fixes: CKEditor insert types, remote video and document
 * view displays (BL-1206).
 *
 * Restricts the media types selectable in the CKEditor "Insert Media"
 * dialog (all except document), drops the generic 'name' field from the
 * remote_video default display, and swaps the generic thumbnail for the
 * document's own image field on the document media_library display.
 *
 * As the highest-numbered hook it runs LAST on every site, so per
 * SearchApiConvergenceHookTest it also re-applies the canonical v2 Search
 * API config.
 */
function bioland_update_9095() {
  $messages = [];

  $messages[] = _bioland_configure_media_embed_allowed_types();
  $messages[] = _bioland_configure_media_view_displays();

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  \Drupal\Core\Cache\Cache::invalidateTags(['rendered', 'config:filter.format.full_html']);

  return implode(' ', array_map('strval', $messages));
}
