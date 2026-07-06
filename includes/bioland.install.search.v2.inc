<?php

/**
 * @file
 * Search API & Facets update functions for the Bioland module (Version 2).
 *
 * Contains new update hooks for configuring Search API indexes and facets
 * with production-ready settings. Uses serialized config data for accuracy.
 */

/*
 * =============================================================================
 * SERIALIZED CONFIG DATA - From production export
 * =============================================================================
 */

/**
 * Get serialized config data for all Search API and Facets configurations.
 *
 * @return array
 *   Associative array of config name => serialized data.
 */
function _bioland_v2_get_serialized_configs() {
  return [
    'facets.facet.content_type' => 'a:27:{s:4:"uuid";s:36:"37d7a921-495a-48a3-803a-7ac059795d7d";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:25:"jsonapi_search_api_facets";}}s:2:"id";s:12:"content_type";s:4:"name";s:12:"Content Type";s:6:"weight";i:0;s:9:"min_count";i:1;s:7:"missing";b:0;s:13:"missing_label";s:6:"others";s:9:"url_alias";s:12:"content_type";s:15:"facet_source_id";s:33:"jsonapi_search_api_facets:content";s:16:"field_identifier";s:3:"tid";s:14:"query_operator";s:2:"or";s:10:"hard_limit";i:0;s:7:"exclude";b:0;s:13:"use_hierarchy";b:0;s:29:"keep_hierarchy_parents_active";b:0;s:9:"hierarchy";a:2:{s:4:"type";s:8:"taxonomy";s:6:"config";a:0:{}}s:16:"expand_hierarchy";b:0;s:38:"enable_parent_when_child_gets_disabled";b:1;s:6:"widget";a:2:{s:4:"type";s:18:"jsonapi_search_api";s:6:"config";a:1:{s:12:"show_numbers";b:1;}}s:14:"empty_behavior";a:1:{s:8:"behavior";s:4:"none";}s:41:"only_visible_when_facet_source_is_visible";b:0;s:20:"show_only_one_result";b:0;s:10:"show_title";b:0;s:17:"processor_configs";a:5:{s:19:"active_widget_order";a:3:{s:12:"processor_id";s:19:"active_widget_order";s:7:"weights";a:1:{s:4:"sort";i:20;}s:8:"settings";a:1:{s:4:"sort";s:4:"DESC";}}s:18:"count_widget_order";a:3:{s:12:"processor_id";s:18:"count_widget_order";s:7:"weights";a:1:{s:4:"sort";i:30;}s:8:"settings";a:1:{s:4:"sort";s:4:"DESC";}}s:19:"hierarchy_processor";a:3:{s:12:"processor_id";s:19:"hierarchy_processor";s:7:"weights";a:1:{s:5:"build";i:100;}s:8:"settings";a:0:{}}s:22:"raw_value_widget_order";a:3:{s:12:"processor_id";s:22:"raw_value_widget_order";s:7:"weights";a:1:{s:4:"sort";i:50;}s:8:"settings";a:1:{s:4:"sort";s:3:"ASC";}}s:21:"url_processor_handler";a:3:{s:12:"processor_id";s:21:"url_processor_handler";s:7:"weights";a:2:{s:9:"pre_query";i:50;s:5:"build";i:15;}s:8:"settings";a:0:{}}}}',

    'facets.facet_source.jsonapi_search_api_facets__content' => 'a:9:{s:4:"uuid";s:36:"1f0d856a-3ec9-46c8-8f93-03f5d8cb7c4a";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:2:"id";s:34:"jsonapi_search_api_facets__content";s:4:"name";s:33:"jsonapi_search_api_facets:content";s:10:"filter_key";s:6:"filter";s:13:"url_processor";s:8:"json_api";s:10:"breadcrumb";a:3:{s:6:"active";b:0;s:6:"before";b:1;s:5:"group";b:0;}}',

    'search_api_db.settings' => 'a:2:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"fEmluJPpUL5dVqkWi0Fw89OlZYLhjQqyZIl6HWCDE1s";}s:28:"autocomplete_max_occurrences";d:0.9;}',

    'search_api.settings' => 'a:6:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"b2zIRm9Jv3SB60NYdZkZHxH8-KdEa-Xa48-4NsIi4lg";}s:18:"default_cron_limit";i:50;s:19:"cron_worker_runtime";i:15;s:15:"default_tracker";s:7:"default";s:18:"tracking_page_size";i:100;s:13:"boost_factors";a:21:{i:0;d:0;i:1;d:0.1;i:2;d:0.2;i:3;d:0.3;i:4;d:0.5;i:5;d:0.6;i:6;d:0.7;i:7;d:0.8;i:8;d:0.9;i:9;d:1;i:10;d:1.1;i:11;d:1.2;i:12;d:1.3;i:13;d:1.4;i:14;d:1.5;i:15;d:2;i:16;d:3;i:17;d:5;i:18;d:8;i:19;d:13;i:20;d:21;}}',

    'search_api.server.file_index' => 'a:9:{s:4:"uuid";s:36:"52b6cbdb-e472-430d-b0f6-f8e31d421ede";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:13:"search_api_db";}}s:2:"id";s:10:"file_index";s:4:"name";s:10:"file-index";s:11:"description";s:0:"";s:7:"backend";s:13:"search_api_db";s:14:"backend_config";a:4:{s:8:"database";s:15:"default:default";s:9:"min_chars";i:1;s:8:"matching";s:7:"partial";s:6:"phrase";s:6:"bigram";}}',

    'search_api.index.content' => 'a:14:{s:4:"uuid";s:36:"5547667a-f807-4bf8-92a6-1e18c619e1fb";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:8:{i:0;s:23:"field.storage.node.body";i:1;s:33:"field.storage.node.field_end_date";i:2;s:30:"field.storage.node.field_order";i:3;s:34:"field.storage.node.field_published";i:4;s:35:"field.storage.node.field_start_date";i:5;s:29:"field.storage.node.field_tags";i:6;s:39:"field.storage.node.field_type_placement";i:7;s:28:"search_api.server.file_index";}s:6:"module";a:1:{i:0;s:4:"node";}}s:2:"id";s:7:"content";s:4:"name";s:7:"content";s:11:"description";s:0:"";s:9:"read_only";b:0;s:14:"field_settings";a:16:{s:4:"body";a:6:{s:5:"label";s:4:"Body";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:4:"body";s:4:"type";s:4:"text";s:5:"boost";d:13;s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:23:"field.storage.node.body";}}}s:7:"changed";a:5:{s:5:"label";s:7:"Changed";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:7:"changed";s:4:"type";s:4:"date";s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:4:"node";}}}s:14:"field_end_date";a:5:{s:5:"label";s:8:"End Date";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:14:"field_end_date";s:4:"type";s:4:"date";s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:33:"field.storage.node.field_end_date";}}}s:11:"field_order";a:5:{s:5:"label";s:5:"Order";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:11:"field_order";s:4:"type";s:7:"integer";s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:30:"field.storage.node.field_order";}}}s:15:"field_published";a:5:{s:5:"label";s:14:"Published Date";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:15:"field_published";s:4:"type";s:4:"date";s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:34:"field.storage.node.field_published";}}}s:16:"field_start_date";a:5:{s:5:"label";s:10:"Start Date";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:16:"field_start_date";s:4:"type";s:4:"date";s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:35:"field.storage.node.field_start_date";}}}s:10:"field_tags";a:5:{s:5:"label";s:4:"Tags";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:10:"field_tags";s:4:"type";s:4:"text";s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:29:"field.storage.node.field_tags";}}}s:8:"language";a:3:{s:5:"label";s:24:"Language (with fallback)";s:13:"property_path";s:22:"language_with_fallback";s:4:"type";s:6:"string";}s:11:"node_grants";a:6:{s:5:"label";s:23:"Node access information";s:13:"property_path";s:22:"search_api_node_grants";s:4:"type";s:6:"string";s:14:"indexed_locked";b:1;s:11:"type_locked";b:1;s:6:"hidden";b:1;}s:7:"promote";a:5:{s:5:"label";s:22:"Promoted to front page";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:7:"promote";s:4:"type";s:7:"boolean";s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:4:"node";}}}s:11:"role_access";a:6:{s:5:"label";s:29:"Role-based access information";s:13:"property_path";s:22:"search_api_role_access";s:4:"type";s:6:"string";s:14:"indexed_locked";b:1;s:11:"type_locked";b:1;s:6:"hidden";b:1;}s:6:"status";a:7:{s:5:"label";s:7:"Boolean";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:6:"status";s:4:"type";s:7:"boolean";s:14:"indexed_locked";b:1;s:11:"type_locked";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:4:"node";}}}s:6:"sticky";a:5:{s:5:"label";s:22:"Sticky at top of lists";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:6:"sticky";s:4:"type";s:7:"boolean";s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:4:"node";}}}s:3:"tid";a:5:{s:5:"label";s:12:"Content Type";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:20:"field_type_placement";s:4:"type";s:7:"integer";s:12:"dependencies";a:1:{s:6:"config";a:1:{i:0;s:39:"field.storage.node.field_type_placement";}}}s:5:"title";a:6:{s:5:"label";s:5:"Title";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:5:"title";s:4:"type";s:4:"text";s:5:"boost";d:21;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:4:"node";}}}s:3:"uid";a:7:{s:5:"label";s:16:"Entity reference";s:13:"datasource_id";s:11:"entity:node";s:13:"property_path";s:3:"uid";s:4:"type";s:7:"integer";s:14:"indexed_locked";b:1;s:11:"type_locked";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:4:"node";}}}}s:19:"datasource_settings";a:1:{s:11:"entity:node";a:2:{s:7:"bundles";a:2:{s:7:"default";b:1;s:8:"selected";a:0:{}}s:9:"languages";a:2:{s:7:"default";b:1;s:8:"selected";a:1:{i:0;s:11:"xx-lolspeak";}}}}s:18:"processor_settings";a:15:{s:7:"add_url";a:0:{}s:16:"aggregated_field";a:0:{}s:14:"content_access";a:1:{s:7:"weights";a:1:{s:16:"preprocess_query";i:-30;}}s:12:"custom_value";a:0:{}s:11:"entity_type";a:0:{}s:11:"html_filter";a:6:{s:7:"weights";a:2:{s:16:"preprocess_index";i:-15;s:16:"preprocess_query";i:-15;}s:10:"all_fields";b:0;s:6:"fields";a:1:{i:0;s:4:"body";}s:5:"title";b:1;s:3:"alt";b:1;s:4:"tags";a:5:{s:1:"b";d:2;s:2:"h1";d:5;s:2:"h2";d:3;s:2:"h3";d:2;s:6:"strong";d:2;}}s:10:"ignorecase";a:3:{s:7:"weights";a:2:{s:16:"preprocess_index";i:-20;s:16:"preprocess_query";i:-20;}s:10:"all_fields";b:0;s:6:"fields";a:2:{i:0;s:4:"body";i:1;s:5:"title";}}s:22:"language_with_fallback";a:0:{}s:18:"number_field_boost";a:2:{s:7:"weights";a:1:{s:16:"preprocess_index";i:0;}s:6:"boosts";a:0:{}}s:13:"rendered_item";a:0:{}s:11:"role_access";a:1:{s:7:"weights";a:1:{s:16:"preprocess_query";i:-30;}}s:7:"stemmer";a:4:{s:7:"weights";a:2:{s:16:"preprocess_index";i:0;s:16:"preprocess_query";i:0;}s:10:"all_fields";b:0;s:6:"fields";a:3:{i:0;s:4:"body";i:1;s:10:"field_tags";i:2;s:5:"title";}s:10:"exceptions";a:2:{s:7:"mexican";s:5:"mexic";s:5:"texan";s:4:"texa";}}s:9:"stopwords";a:4:{s:7:"weights";a:2:{s:16:"preprocess_index";i:-5;s:16:"preprocess_query";i:-2;}s:10:"all_fields";b:0;s:6:"fields";a:2:{i:0;s:4:"body";i:1;s:5:"title";}s:9:"stopwords";a:35:{i:0;s:1:"a";i:1;s:2:"an";i:2;s:3:"and";i:3;s:3:"are";i:4;s:2:"as";i:5;s:2:"at";i:6;s:2:"be";i:7;s:3:"but";i:8;s:2:"by";i:9;s:3:"for";i:10;s:2:"if";i:11;s:2:"in";i:12;s:4:"into";i:13;s:2:"is";i:14;s:2:"it";i:15;s:2:"no";i:16;s:3:"not";i:17;s:2:"of";i:18;s:2:"on";i:19;s:2:"or";i:20;s:1:"s";i:21;s:4:"such";i:22;s:1:"t";i:23;s:4:"that";i:24;s:3:"the";i:25;s:5:"their";i:26;s:4:"then";i:27;s:5:"there";i:28;s:5:"these";i:29;s:4:"they";i:30;s:4:"this";i:31;s:2:"to";i:32;s:3:"was";i:33;s:4:"will";i:34;s:4:"with";}}s:9:"tokenizer";a:7:{s:7:"weights";a:2:{s:16:"preprocess_index";i:-6;s:16:"preprocess_query";i:-6;}s:10:"all_fields";b:0;s:6:"fields";a:3:{i:0;s:4:"body";i:1;s:10:"field_tags";i:2;s:5:"title";}s:6:"spaces";s:0:"";s:7:"ignored";s:3:"._-";s:11:"overlap_cjk";i:1;s:17:"minimum_word_size";s:1:"1";}s:15:"transliteration";a:3:{s:7:"weights";a:2:{s:16:"preprocess_index";i:-20;s:16:"preprocess_query";i:-20;}s:10:"all_fields";b:0;s:6:"fields";a:3:{i:0;s:4:"body";i:1;s:10:"field_tags";i:2;s:5:"title";}}}s:16:"tracker_settings";a:1:{s:7:"default";a:1:{s:14:"indexing_order";s:4:"lifo";}}s:7:"options";a:4:{s:10:"cron_limit";i:50;s:14:"delete_on_fail";b:1;s:14:"index_directly";b:1;s:27:"track_changes_in_references";b:1;}s:6:"server";s:10:"file_index";}',
  ];
}

/*
 * =============================================================================
 * HELPER FUNCTIONS - Search API & Facets V2
 * =============================================================================
 */

/**
 * Apply serialized config data to a configuration object.
 *
 * Creates the config if it doesn't exist, updates if it does.
 * Fails silently on errors.
 *
 * @param string $config_name
 *   The configuration name.
 * @param bool $preserve_uuid
 *   Whether to preserve the existing UUID (only applies to updates).
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_apply_serialized_config($config_name, $preserve_uuid = TRUE) {
  $configs = _bioland_v2_get_serialized_configs();

  if (!isset($configs[$config_name])) {
    // Fail silently - no serialized config available.
    return '';
  }

  try {
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable($config_name);

    // Check if config already exists (for UUID preservation)
    $is_new = $config->isNew();
    $existing_uuid = (!$is_new && $preserve_uuid) ? $config->get('uuid') : NULL;

    // Unserialize and apply the data
    $data = unserialize($configs[$config_name]);

    if ($existing_uuid) {
      $data['uuid'] = $existing_uuid;
    }

    $config->setData($data);
    $config->save(TRUE);

    $action = $is_new ? 'Created' : 'Updated';
    return t('@action @name configuration.', ['@action' => $action, '@name' => $config_name]);
  }
  catch (\Exception $e) {
    // Fail silently, just log.
    \Drupal::logger('bioland')->warning('Could not apply config @name: @message', [
      '@name' => $config_name,
      '@message' => $e->getMessage(),
    ]);
    return '';
  }
}

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
 * Configure facets.facet.content_type using serialized data.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_facets() {
  if (!\Drupal::moduleHandler()->moduleExists('facets')) {
    return t('Facets module is not enabled; skipping configuration.');
  }
  return _bioland_v2_apply_serialized_config('facets.facet.content_type');
}

/**
 * Configure facets.facet_source.jsonapi_search_api_facets__content.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_facet_source() {
  if (!\Drupal::moduleHandler()->moduleExists('facets')) {
    return t('Facets module is not enabled; skipping facet source configuration.');
  }
  return _bioland_v2_apply_serialized_config('facets.facet_source.jsonapi_search_api_facets__content');
}

/**
 * Configure search_api_db.settings using serialized data.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_api_db_settings() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api_db')) {
    return t('Search API DB module is not enabled; skipping configuration.');
  }
  return _bioland_v2_apply_serialized_config('search_api_db.settings', FALSE);
}

/**
 * Configure search_api.settings using serialized data.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_api_settings() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping global settings configuration.');
  }
  return _bioland_v2_apply_serialized_config('search_api.settings', FALSE);
}

/**
 * Configure search_api.server.file_index using serialized data.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_api_server() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping server configuration.');
  }
  return _bioland_v2_apply_serialized_config('search_api.server.file_index');
}

/**
 * Configure search_api.index.content using serialized data.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_configure_search_index() {
  if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
    return t('Search API module is not enabled; skipping configuration.');
  }
  return _bioland_v2_apply_serialized_config('search_api.index.content');
}

/**
 * Delete unwanted facet configurations.
 *
 * Removes facets that are not needed: sticky, status, promoted, language.
 *
 * @return string
 *   Status message.
 */
function _bioland_v2_delete_unwanted_facets() {
  if (!\Drupal::moduleHandler()->moduleExists('facets')) {
    return t('Facets module is not enabled; skipping facet deletion.');
  }

  $facets_to_delete = [
    'facets.facet.sticky',
    'facets.facet.status',
    'facets.facet.promoted',
    'facets.facet.language',
  ];

  $deleted = [];
  $config_factory = \Drupal::configFactory();

  foreach ($facets_to_delete as $config_name) {
    $config = $config_factory->getEditable($config_name);
    if (!$config->isNew()) {
      $config->delete();
      $deleted[] = $config_name;
    }
  }

  if (empty($deleted)) {
    return t('No unwanted facets found to delete.');
  }

  return t('Deleted facet configs: @configs.', ['@configs' => implode(', ', $deleted)]);
}

/**
 * Re-enable indexing and trigger a full reindex using disable/enable approach.
 *
 * This approach forces Search API DB to rebuild its field tables, which
 * resolves issues with missing tables like search_api_db_content_langcode.
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

    // Step 1: Disable the index (clears field tracking in server config)
    if ($index->status()) {
      $index->disable()->save();
      \Drupal::logger('bioland')->notice('Disabled Search API content index for field table rebuild.');
    }

    // Step 2: Re-enable the index (triggers fieldsUpdated() to create tables)
    $index->enable();
    $index->setOption('index_directly', TRUE);
    $index->save();
    \Drupal::logger('bioland')->notice('Re-enabled Search API content index with immediate indexing.');

    // Step 3: Rebuild tracking and mark for reindex
    $index->rebuildTracker();
    $index->reindex();

    // Step 4: Perform indexing (-1 = no limit)
    $indexed = $index->indexItems(-1);

    $message = t('Search index reset complete: disabled, re-enabled (rebuilt field tables), and indexed @count items.', ['@count' => $indexed]);
    \Drupal::logger('bioland')->notice($message);
    return $message;
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->error('Error during search index reset: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Error during search index reset: @message', ['@message' => $e->getMessage()]);
  }
}

/*
 * =============================================================================
 * UPDATE HOOKS - Search API & Facets V2
 * =============================================================================
 */

/**
 * Update Search API and Facets configurations using serialized production data.
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

  // Step 8: Delete unwanted facets (sticky, status, promoted, language)
  $messages[] = _bioland_v2_delete_unwanted_facets();

  // Step 9: Re-enable and reindex
  $messages[] = _bioland_v2_reindex_search();

  return implode(' ', $messages);
}

/**
 * Configure Search API and Facets with production settings (serialized data).
 *
 * @version 1.0.44
 */
function bioland_update_9059() {
  return _bioland_v2_update_search_and_facets_config();
}

/**
 * Reset Search API index by disabling/enabling to rebuild field tables.
 *
 * This fixes missing database tables like search_api_db_content_langcode
 * and search_api_db_content_field_tags.
 *
 * @version 1.0.45
 */
function bioland_update_9060() {
  return _bioland_v2_reindex_search();
}

/**
 * Converge the Search API index and facets on the canonical v2 configuration.
 *
 * This is the single, authoritative "switch" for Search API configuration.
 *
 * Two configuration paths exist in this module for historical reasons:
 *  - The v1 path (bioland.install.search.inc): _bioland_configure_search_api_index()
 *    and _bioland_configure_facets(), run by the legacy bioland_update_9025().
 *    It builds the index/facets programmatically and produces a different shape
 *    than v2 (e.g. tracker order, indexed languages, and it creates a "language"
 *    facet that v2 later deletes).
 *  - The v2 path (this file): _bioland_v2_update_search_and_facets_config(), which
 *    applies the serialized production config and is the canonical source of truth.
 *    It is also what a fresh bioland_install() runs.
 *
 * Because a site's final index state otherwise depends on which subset and order
 * of those legacy hooks its update history happened to run, an index could be left
 * half-configured (v1 as the last writer, or a reindex missed). This hook removes
 * that ambiguity: as the highest-numbered update hook it is guaranteed to run last
 * for EVERY existing site regardless of history, converging the index and facets
 * on the canonical v2 configuration and reindexing.
 *
 * The operation is idempotent and safe to re-run:
 *  - _bioland_v2_apply_serialized_config() creates-or-updates each config and
 *    preserves existing UUIDs, so sites already on v2 get a no-op re-apply.
 *  - Every step guards on the relevant module being enabled.
 *  - It ends with a full reindex, so the index is always left fully populated.
 *
 * Fresh installs baseline their schema at this hook number and therefore skip it
 * (bioland_install() already applies the v2 config directly).
 *
 * @version 1.1.0
 */
function bioland_update_9064() {
  return _bioland_v2_update_search_and_facets_config();
}

/**
 * Terminal convergence: re-apply the canonical Search API v2 config last.
 *
 * The 9071-9073 corrective hooks (translation re-import, term-translation
 * backfill, config seed) outnumber the previous convergence points (9064, and
 * 9065 which also converges), so the "single switch" duty moves here. As the
 * highest-numbered update hook this is guaranteed to run last for every site
 * regardless of update history, re-applying the canonical v2 index/facet
 * configuration and reindexing. Same idempotency guarantees as
 * bioland_update_9064(): create-or-update, UUID-preserving, module-guarded,
 * ends with a full reindex.
 *
 * If you add a higher-numbered hook, it must itself converge on the v2
 * config and SearchApiConvergenceHookTest must be updated to point at it.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9074() {
  return _bioland_v2_update_search_and_facets_config();
}
