<?php

/**
 * @file
 * Search API & Facets install helpers for the Bioland module.
 *
 * Contains helper functions and update hooks for configuring Search API
 * indexes, facets, and related functionality.
 *
 * SUPERSEDED: this is the legacy "v1" configuration path, retained only so the
 * historical update hooks that already ran on existing sites (e.g.
 * bioland_update_9025) remain replayable. The canonical source of truth is now
 * the "v2" path in bioland.install.search.v2.inc, and bioland_update_9064()
 * converges every site on it. Do NOT wire these v1 helpers
 * (_bioland_configure_search_api_index(), _bioland_configure_facets(), etc.)
 * into bioland_install() or any new update hook, or the two paths will fight
 * over the same config and can leave the index half-configured.
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS - Search API & Facets
 * =============================================================================
 */

/**
 * Helper function to add field_order to the Search API "content" index.
 */
function _bioland_add_field_order_to_search_index() {
  if (_bioland_require_module('search_api', 'field_order index addition')) {
    \Drupal::logger('bioland')->notice('Search API module is not enabled; skipping field_order index addition.');
    return;
  }

  try {
    // Load the "content" index.
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('content');

    if (!$index) {
      \Drupal::logger('bioland')->notice('Search API index "content" not found; skipping field_order addition.');
      return;
    }

    // Check if field_order already exists in the index.
    if ($index->getField('field_order')) {
      \Drupal::logger('bioland')->notice('field_order already exists in the "content" index.');
      return;
    }

    // Add field_order to the index.
    $field = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($index, 'field_order', [
        'label' => 'Order',
        'datasource_id' => 'entity:node',
        'property_path' => 'field_order',
        'type' => 'integer',
      ]);

    $index->addField($field);
    $index->save();

    // Note: Reindexing is NOT done here - it will be performed as the final
    // step of installation via _bioland_final_reindex() to avoid multiple
    // reindex operations during install.

    \Drupal::logger('bioland')->notice('Added field_order to the Search API "content" index. Reindex will occur at end of install.');
  }
  catch (\Throwable $e) {
    \Drupal::logger('bioland')->error('Failed to add field_order to Search API index: @message', [
      '@message' => $e->getMessage(),
    ]);
  }
}

/**
 * Helper function to configure Search API content index.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_search_api_index() {
  if ($skip = _bioland_require_module('search_api', 'index configuration')) {
    return $skip;
  }

  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('search_api.index.content');

  if ($config->isNew()) {
    return t('search_api.index.content not found; skipping.');
  }

  // Load the index entity to check and control its state.
  $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
  $index = $index_storage->load('content');

  // Track whether we set read_only to avoid issues on restore.
  $set_read_only = FALSE;
  $original_read_only = FALSE;

  if ($index) {
    // Store original read_only state.
    $original_read_only = $index->isReadOnly();

    // Temporarily set index to read_only to prevent backend fieldsUpdated()
    // calls during config save. This avoids TRUNCATE errors on non-existent
    // tables when adding fields like langcode.
    if (!$original_read_only) {
      $index->set('read_only', TRUE);
      // Save without triggering hooks to minimize side effects.
      $index->save();
      $set_read_only = TRUE;
    }
  }

  // Get the site's default language.
  $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

  // Set langcode to default.
  $config->set('langcode', $default_langcode);
  $config->set('status', TRUE);

  // Set dependencies.
  $config->set('dependencies', [
    'config' => [
      'field.storage.node.body',
      'field.storage.node.field_end_date',
      'field.storage.node.field_order',
      'field.storage.node.field_published',
      'field.storage.node.field_start_date',
      'field.storage.node.field_tags',
      'field.storage.node.field_type_placement',
      'search_api.server.file_index',
    ],
    'module' => ['node'],
  ]);

  // Set field settings.
  $config->set('field_settings', [
    'body' => [
      'label' => 'Body',
      'datasource_id' => 'entity:node',
      'property_path' => 'body',
      'type' => 'text',
      'boost' => 13.0,
      'dependencies' => [
        'config' => ['field.storage.node.body'],
      ],
    ],
    'changed' => [
      'label' => 'Changed',
      'datasource_id' => 'entity:node',
      'property_path' => 'changed',
      'type' => 'date',
      'dependencies' => [
        'module' => ['node'],
      ],
    ],
    'field_end_date' => [
      'label' => 'End Date',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_end_date',
      'type' => 'date',
      'dependencies' => [
        'config' => ['field.storage.node.field_end_date'],
      ],
    ],
    'field_order' => [
      'label' => 'Order',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_order',
      'type' => 'integer',
      'dependencies' => [
        'config' => ['field.storage.node.field_order'],
      ],
    ],
    'field_published' => [
      'label' => 'Published Date',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_published',
      'type' => 'date',
      'dependencies' => [
        'config' => ['field.storage.node.field_published'],
      ],
    ],
    'field_start_date' => [
      'label' => 'Start Date',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_start_date',
      'type' => 'date',
      'dependencies' => [
        'config' => ['field.storage.node.field_start_date'],
      ],
    ],
    'field_tags' => [
      'label' => 'Tags',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_tags',
      'type' => 'text',
      'dependencies' => [
        'config' => ['field.storage.node.field_tags'],
      ],
    ],
    'language' => [
      'label' => 'Language (with fallback)',
      'property_path' => 'language_with_fallback',
      'type' => 'string',
    ],
    'node_grants' => [
      'label' => 'Node access information',
      'property_path' => 'search_api_node_grants',
      'type' => 'string',
      'indexed_locked' => TRUE,
      'type_locked' => TRUE,
      'hidden' => TRUE,
    ],
    'promote' => [
      'label' => 'Promoted to front page',
      'datasource_id' => 'entity:node',
      'property_path' => 'promote',
      'type' => 'boolean',
      'dependencies' => [
        'module' => ['node'],
      ],
    ],
    'role_access' => [
      'label' => 'Role-based access information',
      'property_path' => 'search_api_role_access',
      'type' => 'string',
      'indexed_locked' => TRUE,
      'type_locked' => TRUE,
      'hidden' => TRUE,
    ],
    'status' => [
      'label' => 'Boolean',
      'datasource_id' => 'entity:node',
      'property_path' => 'status',
      'type' => 'boolean',
      'indexed_locked' => TRUE,
      'type_locked' => TRUE,
      'dependencies' => [
        'module' => ['node'],
      ],
    ],
    'sticky' => [
      'label' => 'Sticky at top of lists',
      'datasource_id' => 'entity:node',
      'property_path' => 'sticky',
      'type' => 'boolean',
      'dependencies' => [
        'module' => ['node'],
      ],
    ],
    'tid' => [
      'label' => 'Content Type',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_type_placement',
      'type' => 'integer',
      'dependencies' => [
        'config' => ['field.storage.node.field_type_placement'],
      ],
    ],
    'title' => [
      'label' => 'Title',
      'datasource_id' => 'entity:node',
      'property_path' => 'title',
      'type' => 'text',
      'boost' => 21.0,
      'dependencies' => [
        'module' => ['node'],
      ],
    ],
    'uid' => [
      'label' => 'Entity reference',
      'datasource_id' => 'entity:node',
      'property_path' => 'uid',
      'type' => 'integer',
      'indexed_locked' => TRUE,
      'type_locked' => TRUE,
      'dependencies' => [
        'module' => ['node'],
      ],
    ],
  ]);

  // Get all installed languages on the site.
  $languages = \Drupal::languageManager()->getLanguages();
  $language_ids = array_keys($languages);

  // Set datasource settings.
  $config->set('datasource_settings', [
    'entity:node' => [
      'bundles' => [
        'default' => FALSE,
        'selected' => ['content'],
      ],
      'languages' => [
        'default' => FALSE,
        'selected' => array_values($language_ids),
      ],
    ],
  ]);

  // Set processor settings.
  $config->set('processor_settings', [
    'add_url' => [],
    'aggregated_field' => [],
    'content_access' => [
      'weights' => ['preprocess_query' => -30],
    ],
    'custom_value' => [],
    'entity_type' => [],
    'html_filter' => [
      'weights' => [
        'preprocess_index' => -15,
        'preprocess_query' => -15,
      ],
      'all_fields' => FALSE,
      'fields' => ['body'],
      'title' => FALSE,
      'alt' => FALSE,
      'tags' => [
        'b' => 2.0,
        'h1' => 5.0,
        'h2' => 3.0,
        'h3' => 2.0,
        'strong' => 2.0,
      ],
    ],
    'ignorecase' => [
      'weights' => [
        'preprocess_index' => -20,
        'preprocess_query' => -20,
      ],
      'all_fields' => TRUE,
      'fields' => ['body', 'field_tags', 'language', 'title'],
    ],
    'language_with_fallback' => [],
    'rendered_item' => [],
    'reverse_entity_references' => [],
    'role_access' => [
      'weights' => ['preprocess_query' => -30],
    ],
    'stemmer' => [
      'weights' => [
        'preprocess_index' => 0,
        'preprocess_query' => 0,
      ],
      'all_fields' => TRUE,
      'fields' => ['body', 'field_tags', 'title'],
      'exceptions' => [
        'mexican' => 'mexic',
        'texan' => 'texa',
      ],
    ],
    'stopwords' => [
      'weights' => [
        'preprocess_index' => -5,
        'preprocess_query' => -2,
      ],
      'all_fields' => TRUE,
      'fields' => ['body', 'field_tags', 'title'],
      'stopwords' => [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for',
        'if', 'in', 'into', 'is', 'it', 'no', 'not', 'of', 'on', 'or',
        's', 'such', 't', 'that', 'the', 'their', 'then', 'there',
        'these', 'they', 'this', 'to', 'was', 'will', 'with',
      ],
    ],
    'tokenizer' => [
      'weights' => [
        'preprocess_index' => -6,
        'preprocess_query' => -6,
      ],
      'all_fields' => TRUE,
      'fields' => ['body', 'field_tags', 'title'],
      'spaces' => '',
      'ignored' => '._-',
      'overlap_cjk' => 1,
      'minimum_word_size' => '2',
    ],
    'transliteration' => [
      'weights' => [
        'preprocess_index' => -20,
        'preprocess_query' => -20,
      ],
      'all_fields' => TRUE,
      'fields' => ['body', 'field_tags', 'language', 'title'],
    ],
  ]);

  // Set tracker settings.
  $config->set('tracker_settings', [
    'default' => [
      'indexing_order' => 'fifo',
    ],
  ]);

  // Set options.
  $config->set('options', [
    'cron_limit' => 50,
    'delete_on_fail' => TRUE,
    'index_directly' => TRUE,
    'track_changes_in_references' => TRUE,
  ]);

  $config->set('server', 'file_index');
  $config->save(TRUE);

  // Restore read_only state if we changed it.
  if ($set_read_only && $index) {
    $index->set('read_only', $original_read_only);
    $index->save();
  }

  // Note: Tracking rebuild is intentionally NOT done here during fresh install
  // to avoid indexing before configuration is complete. Manual reindex can be
  // performed after install if needed via: drush sapi-rt content && drush sapi-i content

  \Drupal::logger('bioland')->notice('Configured search_api.index.content with language field.');
  return t('Configured Search API content index with language field.');
}

/**
 * Ensure Search API database tables exist before performing operations.
 *
 * Checks if required Search API DB backend tables exist and creates them
 * if missing. This prevents errors when field configuration changes trigger
 * TRUNCATE operations on non-existent tables.
 *
 * @param \Drupal\search_api\IndexInterface $index
 *   The search index to check.
 *
 * @return bool
 *   TRUE if all required tables exist or were created, FALSE on failure.
 */
function _bioland_ensure_search_api_tables_exist($index) {
  if (!$index) {
    return FALSE;
  }

  try {
    $database = \Drupal::database();
    $schema = $database->schema();

    // Check if the main index table exists.
    $index_table = 'search_api_db_content';
    if (!$schema->tableExists($index_table)) {
      \Drupal::logger('bioland')->notice('Search API table @table does not exist yet; will be created on first index.', [
        '@table' => $index_table,
      ]);
      // This is expected for fresh installs - tables are created when the
      // server's fieldsUpdated() method is called. We return TRUE because
      // this is not an error condition.
      return TRUE;
    }

    // Check for common field tables that might be referenced.
    $field_tables = [
      'search_api_db_content_langcode',
      'search_api_db_content_field_tags',
      'search_api_db_content_title',
      'search_api_db_content_body',
    ];

    $missing_tables = [];
    foreach ($field_tables as $table) {
      if (!$schema->tableExists($table)) {
        $missing_tables[] = $table;
      }
    }

    if (!empty($missing_tables)) {
      \Drupal::logger('bioland')->notice('Some Search API field tables do not exist: @tables. They will be created during indexing.', [
        '@tables' => implode(', ', $missing_tables),
      ]);
      // Return TRUE as tables will be created by the backend when needed.
      return TRUE;
    }

    return TRUE;
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Error checking Search API tables: @message', [
      '@message' => $e->getMessage(),
    ]);
    return FALSE;
  }
}

/**
 * Clear all pending Search API server tasks without executing them.
 *
 * This is used before restoring an index to prevent fieldsUpdated() calls
 * that would fail on non-existent tables. The field tables will be properly
 * created during the subsequent reindex operation.
 *
 * @return string
 *   Status message.
 */
function _bioland_clear_pending_search_api_tasks() {
  if ($skip = _bioland_require_module('search_api', 'clear pending tasks')) {
    return $skip;
  }

  try {
    // Get the task manager service.
    if (!\Drupal::hasService('search_api.server_task_manager')) {
      return t('Search API task manager service not available.');
    }

    $task_manager = \Drupal::service('search_api.server_task_manager');

    // Load the server.
    $server_storage = \Drupal::entityTypeManager()->getStorage('search_api_server');
    $server = $server_storage->load('file_index');

    if (!$server) {
      return t('Search API server "file_index" not found.');
    }

    // Check for and delete pending tasks.
    $pending_count = $task_manager->getCount($server);

    if ($pending_count > 0) {
      $task_manager->delete($server);
      \Drupal::logger('bioland')->notice('Cleared @count pending Search API tasks to allow clean reindex.', [
        '@count' => $pending_count,
      ]);
      return t('Cleared @count pending Search API tasks.', ['@count' => $pending_count]);
    }

    return t('No pending Search API tasks to clear.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->warning('Error clearing Search API tasks: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Warning: Error clearing Search API tasks: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Execute any pending Search API server tasks.
 *
 * Clears pending tasks that might cause errors when executed during
 * install/update operations (like truncating non-existent tables).
 *
 * @return string
 *   Status message.
 */
function _bioland_execute_pending_search_api_tasks() {
  if ($skip = _bioland_require_module('search_api', 'pending tasks')) {
    return $skip;
  }

  try {
    $database = \Drupal::database();
    $schema = $database->schema();

    // Check if search_api_task table exists.
    if (!$schema->tableExists('search_api_task')) {
      return t('Search API task table does not exist; no pending tasks.');
    }

    // Get the task manager service.
    if (!\Drupal::hasService('search_api.server_task_manager')) {
      return t('Search API task manager service not available.');
    }

    $task_manager = \Drupal::service('search_api.server_task_manager');

    // Load the server.
    $server_storage = \Drupal::entityTypeManager()->getStorage('search_api_server');
    $server = $server_storage->load('file_index');

    if (!$server) {
      return t('Search API server "file_index" not found.');
    }

    // Check for pending tasks and handle them safely.
    $pending_count = $task_manager->getCount($server);

    if ($pending_count > 0) {
      \Drupal::logger('bioland')->notice('Found @count pending Search API tasks.', [
        '@count' => $pending_count,
      ]);

      // Try to execute tasks, but catch errors for missing tables.
      try {
        $task_manager->execute($server);
        \Drupal::logger('bioland')->notice('Executed @count pending Search API tasks.', [
          '@count' => $pending_count,
        ]);
      }
      catch (\Exception $task_error) {
        // If task execution fails due to missing tables, delete the tasks
        // instead of leaving them to cause repeated errors.
        $error_message = $task_error->getMessage();
        if (strpos($error_message, 'Base table or view not found') !== FALSE) {
          $task_manager->delete($server);
          \Drupal::logger('bioland')->warning('Cleared @count pending Search API tasks due to missing tables: @message', [
            '@count' => $pending_count,
            '@message' => $error_message,
          ]);
        }
        else {
          throw $task_error;
        }
      }
    }

    return t('Processed Search API pending tasks.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->warning('Error processing Search API tasks: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Warning: Error processing Search API tasks: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Pause immediate indexing on the Search API content index.
 *
 * Called at the start of install to prevent indexing during configuration.
 * Sets index_directly to FALSE so content changes don't trigger immediate indexing.
 * The option will be restored by _bioland_final_reindex() at the end.
 *
 * @return string
 *   Status message.
 */
function _bioland_disable_search_index() {
  if ($skip = _bioland_require_module('search_api', 'pause indexing')) {
    return $skip;
  }

  try {
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('content');

    if (!$index) {
      return t('Search API index "content" not found; skipping pause.');
    }

    // Turn off immediate indexing instead of disabling the index entirely.
    // This avoids entity state issues when re-enabling.
    $index->setOption('index_directly', FALSE);
    $index->save();
    \Drupal::logger('bioland')->notice('Paused immediate indexing on Search API content index during install.');

    return t('Search API content index: immediate indexing paused.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->warning('Could not pause Search API indexing: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Warning: Could not pause indexing: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Final reindex step - MUST be called at the end of install/update.
 *
 * Restores immediate indexing, rebuilds tracking information,
 * and indexes all items. This is the ONLY place where reindexing should be
 * triggered during install to avoid multiple redundant reindex operations.
 *
 * @return string
 *   Status message.
 */
function _bioland_final_reindex() {
  if ($skip = _bioland_require_module('search_api', 'final reindex')) {
    return $skip;
  }

  try {
    // Clear caches to ensure all field definitions are loaded.
    // This is critical when fields were just created during install.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    drupal_flush_all_caches();

    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('content');

    if (!$index) {
      \Drupal::logger('bioland')->notice('Search API index "content" not found; skipping final reindex.');
      return t('Search API index "content" not found; skipping final reindex.');
    }

    // Ensure Search API database tables exist before operations.
    // This prevents errors when truncating tables that don't exist yet.
    $tables_ready = _bioland_ensure_search_api_tables_exist($index);
    if (!$tables_ready) {
      \Drupal::logger('bioland')->warning('Search API tables not ready; skipping reindex.');
      return t('Search API tables not ready; skipping reindex.');
    }

    // Clear pending server tasks BEFORE restoring the index.
    // This prevents $index->save() from triggering fieldsUpdated() on
    // non-existent tables. The tables will be created properly during reindex.
    _bioland_clear_pending_search_api_tasks();

    // Restore immediate indexing (was paused at start of install).
    $index->setOption('index_directly', TRUE);
    $index->save();
    \Drupal::logger('bioland')->notice('Restored immediate indexing on Search API content index.');

    // Rebuild tracking information.
    $index->rebuildTracker();

    // Mark all items for reindexing.
    $index->reindex();

    // Perform the actual indexing (-1 means no limit).
    $indexed = $index->indexItems(-1);

    $message = t('Final reindex complete: restored immediate indexing, rebuilt tracking, and indexed @count items.', [
      '@count' => $indexed,
    ]);
    \Drupal::logger('bioland')->notice($message);
    return $message;
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Error during final reindex: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error during final reindex: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Helper function to rebuild Search API index tracking.
 *
 * Clears and rebuilds tracking information for the content index,
 * marking all items for re-indexing.
 *
 * @return string
 *   Status message.
 */
function _bioland_rebuild_search_index_tracking() {
  if ($skip = _bioland_require_module('search_api', 'tracking rebuild')) {
    return $skip;
  }

  try {
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('content');

    if (!$index) {
      return t('Search API index "content" not found; skipping tracking rebuild.');
    }

    // Clear all tracking data and rebuild.
    $index->rebuildTracker();

    \Drupal::logger('bioland')->notice('Rebuilt tracking information for Search API content index.');
    return t('Rebuilt tracking information for Search API content index.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Error rebuilding Search API tracking: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error rebuilding tracking: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * Helper function to configure facets.
 *
 * Configures facet source and content_type/language facets, removes others.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_facets() {
  if ($skip = _bioland_require_module('facets', 'facet configuration')) {
    return $skip;
  }

  $config_factory = \Drupal::configFactory();
  $messages = [];
  $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

  // 1. Configure facet source.
  $facet_source = $config_factory->getEditable('facets.facet_source.jsonapi_search_api_facets__content');
  if (!$facet_source->isNew()) {
    $facet_source->set('langcode', $default_langcode);
    $facet_source->set('status', TRUE);
    $facet_source->set('id', 'jsonapi_search_api_facets__content');
    $facet_source->set('name', 'jsonapi_search_api_facets:content');
    $facet_source->set('filter_key', 'filter');
    $facet_source->set('url_processor', 'json_api');
    $facet_source->set('breadcrumb', [
      'active' => FALSE,
      'before' => TRUE,
      'group' => FALSE,
    ]);
    $facet_source->save(TRUE);
    $messages[] = t('Configured facet source.');
  }

  // Get shared processor configs used by all facets.
  $processor_configs = _bioland_get_facet_processor_configs();

  // Define facet configurations.
  $facets = [
    'content_type' => [
      'name' => 'Content Type',
      'field_identifier' => 'tid',
      'only_visible_when_facet_source_is_visible' => FALSE,
    ],
    'language' => [
      'name' => 'Language',
      'field_identifier' => 'language',
      'only_visible_when_facet_source_is_visible' => TRUE,
    ],
  ];

  // Configure each facet.
  foreach ($facets as $facet_id => $facet_settings) {
    $facet_config = $config_factory->getEditable('facets.facet.' . $facet_id);
    $facet_config->set('langcode', $default_langcode);
    $facet_config->set('status', TRUE);
    $facet_config->set('dependencies', ['module' => ['jsonapi_search_api_facets']]);
    $facet_config->set('id', $facet_id);
    $facet_config->set('name', $facet_settings['name']);
    $facet_config->set('weight', 0);
    $facet_config->set('min_count', 1);
    $facet_config->set('missing', FALSE);
    $facet_config->set('missing_label', 'others');
    $facet_config->set('url_alias', $facet_id);
    $facet_config->set('facet_source_id', 'jsonapi_search_api_facets:content');
    $facet_config->set('field_identifier', $facet_settings['field_identifier']);
    $facet_config->set('query_operator', 'or');
    $facet_config->set('hard_limit', 0);
    $facet_config->set('exclude', FALSE);
    $facet_config->set('use_hierarchy', FALSE);
    $facet_config->set('keep_hierarchy_parents_active', FALSE);
    $facet_config->set('hierarchy', ['type' => 'taxonomy', 'config' => []]);
    $facet_config->set('expand_hierarchy', FALSE);
    $facet_config->set('enable_parent_when_child_gets_disabled', TRUE);
    $facet_config->set('widget', ['type' => 'jsonapi_search_api', 'config' => ['show_numbers' => TRUE]]);
    $facet_config->set('empty_behavior', ['behavior' => 'none']);
    $facet_config->set('only_visible_when_facet_source_is_visible', $facet_settings['only_visible_when_facet_source_is_visible']);
    $facet_config->set('show_only_one_result', FALSE);
    $facet_config->set('show_title', FALSE);
    $facet_config->set('processor_configs', $processor_configs);
    $facet_config->save(TRUE);
    $messages[] = t('Configured @name facet.', ['@name' => $facet_settings['name']]);
  }

  // 4. Remove other facets (keep only content_type and language).
  $allowed_facets = array_keys($facets);
  $facet_configs = $config_factory->listAll('facets.facet.');

  foreach ($facet_configs as $config_name) {
    $facet_id = str_replace('facets.facet.', '', $config_name);
    if (!in_array($facet_id, $allowed_facets)) {
      $config_factory->getEditable($config_name)->delete();
      $messages[] = t('Removed facet: @id.', ['@id' => $facet_id]);
    }
  }

  return implode(' ', $messages);
}

/**
 * Get shared processor configs for facets.
 *
 * @return array
 *   Processor configuration array.
 */
function _bioland_get_facet_processor_configs() {
  return [
    'active_widget_order' => [
      'processor_id' => 'active_widget_order',
      'weights' => ['sort' => 20],
      'settings' => ['sort' => 'DESC'],
    ],
    'count_widget_order' => [
      'processor_id' => 'count_widget_order',
      'weights' => ['sort' => 30],
      'settings' => ['sort' => 'DESC'],
    ],
    'display_value_widget_order' => [
      'processor_id' => 'display_value_widget_order',
      'weights' => ['sort' => 40],
      'settings' => ['sort' => 'ASC'],
    ],
    'hierarchy_processor' => [
      'processor_id' => 'hierarchy_processor',
      'weights' => ['build' => 100],
      'settings' => [],
    ],
    'url_processor_handler' => [
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => 50, 'build' => 15],
      'settings' => [],
    ],
  ];
}

/*
 * =============================================================================
 * UPDATE HOOKS - Search API & Facets
 * =============================================================================
 */

/**
 * Add field_order to Search API content index.
 */
function bioland_update_9011() {
  // Check if Search API module is enabled.
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping field_order index addition.');
  }

  // Load the "content" index.
  $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
  $index = $index_storage->load('content');

  if (!$index) {
    return t('Search API index "content" not found; skipping field_order addition.');
  }

  // Check if field_order already exists in the index.
  if ($index->getField('field_order')) {
    return t('field_order already exists in the "content" index.');
  }

  // Add field_order to the index.
  $field = \Drupal::getContainer()
    ->get('search_api.fields_helper')
    ->createField($index, 'field_order', [
      'label' => 'Order',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_order',
      'type' => 'integer',
    ]);

  $index->addField($field);
  $index->save();

  // Note: Reindexing is deferred. Run 'drush sapi-rt content && drush sapi-i content'
  // after all update hooks complete, or it will occur automatically at end of install.

  return t('Added field_order to the Search API "content" index. Run "drush sapi-rt content && drush sapi-i content" to reindex.');
}

/**
 * Add langcode field to content form sidebar.
 *
 * @version 1.0.30
 */
function bioland_update_9026() {
  return _bioland_configure_langcode_form_display();
}
