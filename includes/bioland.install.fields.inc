<?php

/**
 * @file
 * Field Configuration related install, update and helper functions for the Bioland module.
 */

/**
 * Clear help text (description) on specified fields.
 *
 * @param string $entity_type
 *   The entity type (e.g., 'node').
 * @param string $bundle
 *   The bundle name (e.g., 'content').
 * @param array $field_names
 *   Array of field names to clear help text from.
 *
 * @return int
 *   Number of fields updated.
 */
function _bioland_clear_field_help_text($entity_type, $bundle, array $field_names) {
  $updated = 0;

  foreach ($field_names as $field_name) {
    /** @var \Drupal\field\Entity\FieldConfig|null $field */
    $field = \Drupal\field\Entity\FieldConfig::loadByName($entity_type, $bundle, $field_name);
    if ($field && $field->getDescription() !== '') {
      $field->setDescription('');
      $field->save();
      $updated++;
    }
  }

  return $updated;
}

/**
 * Configure content fields: non-translatable, remove defaults, Linkit widget.
 *
 * @return array
 *   Array of status messages.
 */
function _bioland_configure_content_fields() {
  $messages = [];

  // 1. Set specific fields as non-translatable.
  $non_translatable_fields = [
    'field_attachments',
    'field_components',
    'field_type_placement',
    'field_tags',
  ];

  foreach ($non_translatable_fields as $field_name) {
    $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'content', $field_name);
    if ($field && $field->isTranslatable()) {
      $field->setTranslatable(FALSE);
      $field->save();
      $messages[] = t('Set @field as non-translatable.', ['@field' => $field_name]);
    }
  }

  // 2. Remove default value from field_published.
  $field_published = \Drupal\field\Entity\FieldConfig::loadByName('node', 'content', 'field_published');
  if ($field_published) {
    $default_value = $field_published->getDefaultValueLiteral();
    if (!empty($default_value)) {
      $field_published->setDefaultValue([]);
      $field_published->save();
      $messages[] = t('Removed default value from field_published.');
    }
  }

  // 3. Configure field_url to use Linkit widget with auto-populate link text.
  if (\Drupal::moduleHandler()->moduleExists('linkit')) {
    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.content.default');

    if ($form_display) {
      $url_component = $form_display->getComponent('field_url');
      if ($url_component) {
        $form_display->setComponent('field_url', [
          'type' => 'linkit',
          'weight' => $url_component['weight'] ?? 0,
          'settings' => [
            'linkit_profile' => 'default',
            'linkit_auto_link_text' => TRUE,
            'placeholder_url' => '',
            'placeholder_title' => '',
          ],
          'third_party_settings' => $url_component['third_party_settings'] ?? [],
        ]);
        $form_display->save();
        $messages[] = t('Configured field_url with Linkit widget (auto-populate link text enabled).');
      }
    }
  }

  if (!empty($messages)) {
    \Drupal::logger('bioland')->notice('Content field configuration: @messages', [
      '@messages' => implode(' ', $messages),
    ]);
  }

  return $messages;
}

/**
 * Install optional field configurations from config/optional directory.
 *
 * Uses the entity API to create field storage and field config entities,
 * which properly creates the database tables.
 *
 * @return string
 *   Status messages about installed configurations.
 */
function _bioland_install_optional_field_configs() {
  $messages = [];
  $config_path = \Drupal::service('extension.list.module')->getPath('bioland') . '/config/optional';
  $source = new \Drupal\Core\Config\FileStorage($config_path);

  // List of optional field storage configs to install (in dependency order).
  $field_storages = [
    'field.storage.node.field_order',
  ];

  // List of optional field instance configs to install.
  $field_configs = [
    'field.field.node.content.field_order',
    'field.field.node.content.field_url',
  ];

  // Install field storages first using entity API (creates database tables).
  foreach ($field_storages as $config_name) {
    try {
      // Extract field name from config name (e.g., 'field.storage.node.field_order' -> 'field_order').
      $parts = explode('.', $config_name);
      $field_name = end($parts);
      $entity_type = $parts[2]; // 'node'

      // Check if field storage already exists.
      $existing_storage = \Drupal\field\Entity\FieldStorageConfig::loadByName($entity_type, $field_name);
      if (!$existing_storage) {
        $config_data = $source->read($config_name);
        if ($config_data) {
          // Create field storage entity (this creates the database table).
          $field_storage = \Drupal\field\Entity\FieldStorageConfig::create($config_data);
          $field_storage->save();
          $messages[] = t('Created field storage @config.', ['@config' => $config_name]);
        }
      }
      else {
        $messages[] = t('Field storage @config already exists.', ['@config' => $config_name]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->warning('Could not install field storage @config: @message', [
        '@config' => $config_name,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  // Install field instance configs using entity API.
  foreach ($field_configs as $config_name) {
    try {
      // Extract field name and bundle from config name.
      // e.g., 'field.field.node.content.field_order' -> entity_type='node', bundle='content', field_name='field_order'
      $parts = explode('.', $config_name);
      $entity_type = $parts[2]; // 'node'
      $bundle = $parts[3]; // 'content'
      $field_name = $parts[4]; // 'field_order'

      // Check if field config already exists.
      $existing_field = \Drupal\field\Entity\FieldConfig::loadByName($entity_type, $bundle, $field_name);
      if (!$existing_field) {
        $config_data = $source->read($config_name);
        if ($config_data) {
          // Create field config entity.
          $field_config = \Drupal\field\Entity\FieldConfig::create($config_data);
          $field_config->save();
          $messages[] = t('Created field config @config.', ['@config' => $config_name]);
        }
      }
      else {
        $messages[] = t('Field config @config already exists.', ['@config' => $config_name]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->warning('Could not install field config @config: @message', [
        '@config' => $config_name,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  // Clear field cache to pick up changes.
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

  if (!empty($messages)) {
    \Drupal::logger('bioland')->notice('Installed optional field configs: @configs', [
      '@configs' => implode(', ', array_merge($field_storages, $field_configs)),
    ]);
  }

  return implode("\n", $messages);
}

/*
 * =============================================================================
 * UPDATE HOOKS - Field Configuration
 * =============================================================================
 */

/**
 * Install optional field configs (field_order, field_url).
 */
function bioland_update_9004() {
  // Ensure the node module and required content type exist.
  if (!\Drupal::moduleHandler()->moduleExists('node')) {
    return t('Skipped field_order creation: Node module is not enabled.');
  }

  $content_type = \Drupal\node\Entity\NodeType::load('content');
  if (!$content_type) {
    return t('Skipped field_order creation: Content type "content" does not exist.');
  }

  // Create field storage if it does not exist.
  $field_storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_order');
  if (!$field_storage) {
    $field_storage = \Drupal\field\Entity\FieldStorageConfig::create([
      'field_name' => 'field_order',
      'entity_type' => 'node',
      'type' => 'integer',
      'settings' => [
        'unsigned' => FALSE,
        'size' => 'normal',
      ],
      'cardinality' => 1,
      'translatable' => FALSE,
    ]);
    $field_storage->save();
  }

  // Create field instance on the "content" bundle if it does not exist.
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'content', 'field_order');
  if (!$field) {
    $field = \Drupal\field\Entity\FieldConfig::create([
      'field_name' => 'field_order',
      'entity_type' => 'node',
      'bundle' => 'content',
      'label' => 'Order',
      'description' => '',
      'required' => FALSE,
      'translatable' => FALSE,
      'settings' => [
        'min' => NULL,
        'max' => NULL,
        'prefix' => '',
        'suffix' => '',
      ],
    ]);
    $field->save();
  }

  // Ensure the field is visible in the default form display.
  $entity_form_display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('node.content.default');

  if ($entity_form_display && !$entity_form_display->getComponent('field_order')) {
    $entity_form_display->setComponent('field_order', [
      'type' => 'number',
      'weight' => 10,
    ]);
    $entity_form_display->save();
  }

  return t('Ensured field_order field exists on the Content content type and is visible on the form display.');
}

/**
 * Configure node body field settings - clear help text on body and field_attachments.
 */
function bioland_update_9005() {
  $updated = _bioland_clear_field_help_text('node', 'content', ['body', 'field_attachments']);

  if ($updated > 0) {
    return t('Cleared help text on @count field(s) (body, field_attachments) for the Content content type.', ['@count' => $updated]);
  }

  return t('No help text found to clear on body or field_attachments for the Content content type.');
}

/**
 * Configure content fields - set max limit of 10000 on field_order field.
 */
function bioland_update_9007() {
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'content', 'field_order');

  if (!$field) {
    return t('field_order field does not exist; skipping max limit update.');
  }

  $settings = $field->getSettings();
  $settings['max'] = 10000;
  $field->setSettings($settings);
  $field->save();

  return t('Set max limit of 10000 on field_order field.');
}

/**
 * Clear field help text - add Promotion Options help message configuration.
 */
function bioland_update_9014() {
  $config_factory = \Drupal::configFactory();
  $editable = $config_factory->getEditable('bioland.settings');

  // Check if promotion_text is already set
  if ($editable->get('help_comments.promotion_text') !== NULL) {
    return t('Promotion options help text already exists; no changes made.');
  }

  // Get all available languages
  $language_manager = \Drupal::languageManager();
  $languages = $language_manager->getLanguages();
  $default_langcode = $language_manager->getDefaultLanguage()->getId();
  
  // Initialize translation array
  $promotion_translations = [];
  foreach ($languages as $langcode => $language) {
    if ($langcode === $default_langcode || $langcode === 'en-x-lolspeak') {
      continue;
    }
    $promotion_translations[$langcode] = '';
  }

  // Set default promotion help text (HTML allowed)
  $promotion_text = '<b>Promoted to front page:</b>
<ul>
<li>Content appears in featured listings, homepage blocks, or "promoted" views</li>
<li>Useful for highlighting important or timely content</li>
<li>Multiple items can be promoted simultaneously</li>
</ul>

<b>Sticky at top of lists:</b>
<ul>
<li>Content stays pinned at the top of content listings</li>
<li>Remains at top regardless of publication date</li>
<li>Multiple sticky items appear before non-sticky content</li>
</ul>

<b>Using both:</b> Content can be both promoted AND sticky for maximum visibility.';

  $editable->set('help_comments.promotion_text', $promotion_text);
  $editable->set('help_comments.promotion_translations', $promotion_translations);
  $editable->save();

  return t('Added Promotion Options help message configuration.');
}

/**
 * Reconfigure content fields - update field_url label from 'Url' to 'URL'.
 */
function bioland_update_9024() {
  $field_config = \Drupal\field\Entity\FieldConfig::loadByName('node', 'content', 'field_url');
  
  if ($field_config) {
    $field_config->setLabel('URL');
    $field_config->save();
    return t('Updated field_url label to "URL".');
  }
  
  return t('field_url does not exist, skipping update.');
}

/**
 * Fix content fields - install/update optional field configurations.
 */
function bioland_update_9047() {
  $messages = [];
  
  // Install/update optional field configurations.
  $result = _bioland_install_optional_field_configs();
  if ($result) {
    $messages[] = $result;
  }
  
  // Configure full_html editor toolbar.
  $result = _bioland_configure_full_html_editor_toolbar();
  if ($result) {
    $messages[] = $result;
  }
  
  // Rebuild router to register view routes and fix local task warnings.
  \Drupal::service('router.builder')->rebuild();
  $messages[] = t('Rebuilt router to register view routes.');
  
  return implode("\n", $messages);
}

/**
 * Force the fixed additional tag content type mappings (BL-832).
 */
function bioland_update_9077() {
  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  $changed = [];
  $messages = [];

  // Plain strings, not t() - the translation system is not dependable mid-update.
  foreach (\Drupal\bioland\Service\BiolandAdditionalTagDefaults::CONTENT_TYPES as $key => $content_types) {
    $existing = $config->get('additional_tags.' . $key);

    // Record what was overwritten; the previous values are otherwise unrecoverable.
    if ($existing !== $content_types) {
      $changed[] = $key . ': ' . implode(',', (array) $existing) . ' -> ' . implode(',', $content_types);
    }

    $config->set('additional_tags.' . $key, $content_types);
  }

  $config->save();

  $messages[] = $changed
    ? 'Reset the additional tag content type mappings to their fixed values (' . implode('; ', $changed) . ').'
    : 'Additional tag content type mappings already match their fixed values.';

  // Converge canonical Search API v2 config last (highest-numbered hook).
  // Drupal runs update hooks in ascending order, so this hook is now the last
  // writer for every site and inherits the convergence duty from 9076.
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Make the remote video image and its alt text optional (BL-815).
 *
 * Editors adding a remote video had to upload an image and alt text on top of
 * the name and URL. Core already fills the `thumbnail` base field from the
 * oEmbed provider, so the site-owned field_media_image becomes an optional
 * override. That field config is not shipped by this module, so this edits
 * the live config: every image field on media/remote_video that
 * BiolandRemoteVideoImageField::shouldProcess() selects is made optional,
 * its alt text stops being required (alt_field stays as configured), and it
 * gets help text when it has none. Idempotent; skips cleanly when the media
 * module, the remote_video media type, or the fields are absent.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every site,
 * so per SearchApiConvergenceHookTest it must also re-apply the canonical v2
 * Search API config so the last writer never leaves a half-configured index.
 *
 * @see \Drupal\bioland\BiolandRemoteVideoImageField
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9088() {
  // Plain strings, not t() - the translation system is not dependable
  // mid-update.
  $messages = [];
  $etm = \Drupal::entityTypeManager();

  if (!\Drupal::moduleHandler()->moduleExists('media') || !$etm->getStorage('media_type')->load('remote_video')) {
    $messages[] = 'Skipped remote video image update: the remote_video media type does not exist.';
  }
  else {
    $fields = $etm->getStorage('field_config')->loadByProperties([
      'entity_type' => 'media',
      'bundle' => 'remote_video',
      'field_type' => 'image',
    ]);

    $updated = [];
    foreach ($fields as $field) {
      /** @var \Drupal\field\FieldConfigInterface $field */
      if (!\Drupal\bioland\BiolandRemoteVideoImageField::qualifies($field->getTargetEntityTypeId(), $field->getTargetBundle(), $field->getType())
        || !\Drupal\bioland\BiolandRemoteVideoImageField::shouldProcess($field->getName(), (bool) $field->isRequired())) {
        continue;
      }

      $plan = \Drupal\bioland\BiolandRemoteVideoImageField::plan((bool) $field->isRequired(), $field->getSettings(), (string) $field->getDescription());
      if (!$plan['changed']) {
        continue;
      }

      $field->setRequired($plan['required']);
      $field->setSetting('alt_field_required', $plan['settings']['alt_field_required']);
      $field->setDescription($plan['description']);
      $field->save();
      $updated[] = $field->getName();
    }

    if ($updated) {
      $message = 'Made the remote video image optional (image and alt text no longer required): ' . implode(', ', $updated) . '.';
      \Drupal::logger('bioland')->notice($message);
      $messages[] = $message;
    }
    else {
      $messages[] = $fields
        ? 'Remote video image fields are already optional; left untouched.'
        : 'Skipped remote video image update: no image field exists on the remote_video media type.';
    }
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * BL-1191: removes the field_website_image field an earlier revision of this
 * feature created on node.content. Screenshots attach to the existing
 * Attachments field (field_attachments) instead, so the extra field must not
 * exist. Idempotent: a no-op when the field was never created.
 *
 * @return string
 *   A plain-string status message (no t() - callers include update hooks).
 */
function _bioland_remove_website_image_field() {
  $field_storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_website_image');
  if (!$field_storage) {
    return 'No field_website_image field to remove.';
  }
  // Deleting the storage also deletes every FieldConfig instance and drops
  // the field from the form and view displays.
  $field_storage->delete();
  return 'Removed the obsolete field_website_image field from the Content content type.';
}

/**
 * BL-1191: website screenshot config default, ConvertAPI requirement, and
 * removal of the obsolete field_website_image field.
 *
 * Hook number 9089 is reserved by open PR #65 (BL-917); 9090/9091 were
 * pre-assigned to the parallel BL-1191/BL-1192 branches, so this hook is
 * deliberately 9090, not the next-plus-one from the recount.
 */
function bioland_update_9090() {
  if (!class_exists('\\ConvertApi\\ConvertApi')) {
    throw new \Drupal\Core\Utility\UpdateException('convertapi/convertapi-php is not installed; run composer require convertapi/convertapi-php --with-all-dependencies');
  }

  $messages = [];
  $messages[] = _bioland_remove_website_image_field();

  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  if ($config->get('enable_url_screenshot') === NULL) {
    $config->set('enable_url_screenshot', TRUE)->save();
    $messages[] = 'Seeded the website screenshot switch (enable_url_screenshot) as enabled.';
  }
  else {
    $messages[] = 'Website screenshot switch already present; left untouched.';
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Ensures the BL-1192 document-preview feature is ready (idempotent).
 *
 * Called from bioland_install() and bioland_update_9091(). It never performs
 * a conversion; it only checks the ConvertAPI library is installed and,
 * separately, widens the media.document Image field's max_resolution when it
 * is empty or unhelpfully small, so a generated 1024px-wide preview is not
 * rejected by the field's own constraints.
 *
 * @return string[]
 *   Plain-string status messages.
 *
 * @throws \Drupal\Core\Utility\UpdateException
 *   When the convertapi/convertapi-php library is not installed. An update
 *   hook cannot run composer; it can only refuse to continue.
 */
function _bioland_ensure_document_preview_ready() {
  $messages = [];

  if (!class_exists('\ConvertApi\ConvertApi')) {
    throw new \Drupal\Core\Utility\UpdateException('The convertapi/convertapi-php library is required for document previews. Run: composer require convertapi/convertapi-php --with-all-dependencies');
  }

  $field = \Drupal\field\Entity\FieldConfig::loadByName('media', 'document', \Drupal\bioland\BiolandDocumentPreviewPolicy::IMAGE_FIELD);

  if (!$field) {
    $messages[] = 'media.document Image field config not found; skipped the max_resolution check.';
    return $messages;
  }

  $settings = $field->getSettings();
  $max_resolution = $settings['max_resolution'] ?? '';
  $needs_widening = TRUE;

  if (preg_match('/^(\d+)x(\d+)$/', (string) $max_resolution, $m)) {
    $needs_widening = ((int) $m[1] <= 128 && (int) $m[2] <= 128);
  }

  if ($needs_widening) {
    $settings['max_resolution'] = '1600x1600';
    $field->set('settings', $settings);
    $field->save();
    $messages[] = 'Widened media.document Image field max_resolution to 1600x1600 for document previews.';
  }
  else {
    $messages[] = 'media.document Image field max_resolution already adequate; left untouched.';
  }

  return $messages;
}

/**
 * Ensures document previews are ready and converges the canonical v2 config.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every
 * site, so per SearchApiConvergenceHookTest it must also re-apply the
 * canonical v2 Search API config.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 * @see _bioland_ensure_document_preview_ready()
 */
function bioland_update_9091() {
  $messages = _bioland_ensure_document_preview_ready();
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
