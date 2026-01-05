<?php

/**
 * @file
 * Search API & Facets update functions for the Bioland module (Version 2).
 *
 * Contains new update hooks for configuring Search API indexes and facets
 * with production-ready settings.
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS - Search API & Facets V2
 * =============================================================================
 */

/**
 * Pause immediate indexing on the Search API content index.
 *
 * @return array
 *   Array with 'success' boolean and 'message' string.
 */
function _bioland_v2_pause_search_indexing() {
  $result = ['success' => FALSE, 'message' => ''];
  
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    $result['message'] = t('Search API module is not enabled.');
    return $result;
  }
  
  try {
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('content');
    
    if ($index) {
      $index->setOption('index_directly', FALSE);
      $index->save();
      $result['success'] = TRUE;
      $result['message'] = t('Paused immediate indexing during configuration.');
    }
    else {
      $result['message'] = t('Search API index "content" not found.');
    }
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->warning('Could not pause indexing: @message', [
      '@message' => $e->getMessage(),
    ]);
    $result['message'] = t('Error pausing indexing: @message', ['@message' => $e->getMessage()]);
  }
  
  return $result;
}

/**
 * Configure facets with production settings.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_facets() {
  if (!\Drupal::moduleHandler()->moduleExists('facets')) {
    return t('Facets module is not enabled; skipping configuration.');
  }
  
  $config_factory = \Drupal::configFactory();
  $facet_config = $config_factory->getEditable('facets.facet.content_type');
  
  if ($facet_config->isNew()) {
    return t('Facet config not found; skipping.');
  }
  
  $facet_config->set('processor_configs', [
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
    'hierarchy_processor' => [
      'processor_id' => 'hierarchy_processor',
      'weights' => ['build' => 100],
      'settings' => [],
    ],
    'raw_value_widget_order' => [
      'processor_id' => 'raw_value_widget_order',
      'weights' => ['sort' => 50],
      'settings' => ['sort' => 'ASC'],
    ],
    'url_processor_handler' => [
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => 50, 'build' => 15],
      'settings' => [],
    ],
  ]);
  
  $facet_config->save(TRUE);
  
  return t('Updated facets.facet.content_type configuration.');
}

/**
 * Configure facet source with production settings.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_facet_source() {
  if (!\Drupal::moduleHandler()->moduleExists('facets')) {
    return t('Facets module is not enabled; skipping facet source configuration.');
  }
  
  $config_factory = \Drupal::configFactory();
  $facet_source_config = $config_factory->getEditable('facets.facet_source.jsonapi_search_api_facets__content');
  
  if ($facet_source_config->isNew()) {
    return t('Facet source config not found; skipping.');
  }
  
  $facet_source_config->set('filter_key', 'filter');
  $facet_source_config->set('url_processor', 'json_api');
  $facet_source_config->set('breadcrumb', [
    'active' => FALSE,
    'before' => TRUE,
    'group' => FALSE,
  ]);
  
  $facet_source_config->save(TRUE);
  
  return t('Updated facets.facet_source.jsonapi_search_api_facets__content configuration.');
}

/**
 * Configure Search API database settings.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_api_db_settings() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api_db')) {
    return t('Search API DB module is not enabled; skipping configuration.');
  }
  
  $config_factory = \Drupal::configFactory();
  $db_config = $config_factory->getEditable('search_api_db.settings');
  
  $db_config->set('autocomplete_max_occurrences', 0.9);
  $db_config->save(TRUE);
  
  return t('Updated search_api_db.settings configuration.');
}

/**
 * Configure Search API global settings.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_api_settings() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping global settings configuration.');
  }
  
  $config_factory = \Drupal::configFactory();
  $api_config = $config_factory->getEditable('search_api.settings');
  
  $api_config->set('default_cron_limit', 50);
  $api_config->set('cron_worker_runtime', 15);
  $api_config->set('default_tracker', 'default');
  $api_config->set('tracking_page_size', 100);
  $api_config->set('boost_factors', [
    0 => 0.0,
    1 => 0.1,
    2 => 0.2,
    3 => 0.3,
    4 => 0.5,
    5 => 0.6,
    6 => 0.7,
    7 => 0.8,
    8 => 0.9,
    9 => 1.0,
    10 => 1.1,
    11 => 1.2,
    12 => 1.3,
    13 => 1.4,
    14 => 1.5,
    15 => 2.0,
    16 => 3.0,
    17 => 5.0,
    18 => 8.0,
    19 => 13.0,
    20 => 21.0,
  ]);
  
  $api_config->save(TRUE);
  
  return t('Updated search_api.settings configuration.');
}

/**
 * Configure Search API server settings.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_api_server() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping server configuration.');
  }
  
  $config_factory = \Drupal::configFactory();
  $server_config = $config_factory->getEditable('search_api.server.file_index');
  
  if ($server_config->isNew()) {
    return t('Search API server config not found; skipping.');
  }
  
  $server_config->set('backend_config', [
    'database' => 'default:default',
    'min_chars' => 1,
    'matching' => 'partial',
    'phrase' => 'bigram',
  ]);
  
  $server_config->save(TRUE);
  
  return t('Updated search_api.server.file_index configuration.');
}

/**
 * Configure Search API index with production settings.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_index() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping configuration.');
  }
  
  $config_factory = \Drupal::configFactory();
  $index_config = $config_factory->getEditable('search_api.index.content');
  
  if ($index_config->isNew()) {
    return t('Search API index config not found; skipping.');
  }
  
  // Update datasource_settings
  $index_config->set('datasource_settings', [
    'entity:node' => [
      'bundles' => [
        'default' => TRUE,
        'selected' => [],
      ],
      'languages' => [
        'default' => TRUE,
        'selected' => ['xx-lolspeak'],
      ],
    ],
  ]);
  
  // Update processor_settings
  $index_config->set('processor_settings', [
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
      'title' => TRUE,
      'alt' => TRUE,
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
      'all_fields' => FALSE,
      'fields' => ['body', 'title'],
    ],
    'language_with_fallback' => [],
    'number_field_boost' => [
      'weights' => ['preprocess_index' => 0],
      'boosts' => [],
    ],
    'rendered_item' => [],
    'role_access' => [
      'weights' => ['preprocess_query' => -30],
    ],
    'stemmer' => [
      'weights' => [
        'preprocess_index' => 0,
        'preprocess_query' => 0,
      ],
      'all_fields' => FALSE,
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
      'all_fields' => FALSE,
      'fields' => ['body', 'title'],
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
      'all_fields' => FALSE,
      'fields' => ['body', 'field_tags', 'title'],
      'spaces' => '',
      'ignored' => '._-',
      'overlap_cjk' => 1,
      'minimum_word_size' => '1',
    ],
    'transliteration' => [
      'weights' => [
        'preprocess_index' => -20,
        'preprocess_query' => -20,
      ],
      'all_fields' => FALSE,
      'fields' => ['body', 'field_tags', 'title'],
    ],
  ]);
  
  // Update tracker_settings
  $index_config->set('tracker_settings', [
    'default' => [
      'indexing_order' => 'lifo',
    ],
  ]);
  
  $index_config->save(TRUE);
  
  return t('Updated search_api.index.content configuration.');
}

/**
 * Re-enable immediate indexing and perform full reindex.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_reindex_search() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping reindex.');
  }
  
  try {
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    drupal_flush_all_caches();
    
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('content');
    
    if (!$index) {
      return t('Search API index "content" not found; skipping reindex.');
    }
    
    // Restore immediate indexing
    $index->setOption('index_directly', TRUE);
    $index->save();
    
    // Rebuild tracking
    $index->rebuildTracker();
    
    // Mark all items for reindexing
    $index->reindex();
    
    // Perform indexing
    $indexed = $index->indexItems(-1);
    
    return t('Restored immediate indexing and indexed @count items.', ['@count' => $indexed]);
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Error during reindex: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error during reindex: @message', ['@message' => $e->getMessage()]);
  }
}

/*
 * =============================================================================
 * UPDATE HOOKS - Search API & Facets V2
 * =============================================================================
 */

/**
 * Update Search API and Facets configurations with production settings.
 *
 * This is a helper function called by update hooks.
 *
 * @return string
 *   Combined status messages.
 */
function _bioland_v2_update_search_and_facets_config() {
  $messages = [];
  
  // Step 1: Pause indexing during configuration
  $pause_result = _bioland_v2_pause_search_indexing();
  if ($pause_result['message']) {
    $messages[] = $pause_result['message'];
  }
  
  // Step 2: Configure Search API global settings
  $messages[] = _bioland_v2_configure_search_api_settings();
  
  // Step 3: Configure Search API DB settings
  $messages[] = _bioland_v2_configure_search_api_db_settings();
  
  // Step 4: Configure Search API server
  $messages[] = _bioland_v2_configure_search_api_server();
  
  // Step 5: Configure search index
  $messages[] = _bioland_v2_configure_search_index();
  
  // Step 6: Configure facets
  $messages[] = _bioland_v2_configure_facets();
  
  // Step 7: Configure facet source
  $messages[] = _bioland_v2_configure_facet_source();
  
  // Step 8: Re-enable and reindex
  $messages[] = _bioland_v2_reindex_search();
  
  return implode(' ', $messages);
}

/**
 * Configure Search API and Facets with production settings.
 *
 * @version 1.0.43
 */
function bioland_update_9059() {
  return _bioland_v2_update_search_and_facets_config();
}
