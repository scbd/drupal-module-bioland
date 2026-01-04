<?php

/**
 * @file
 * JSON:API configuration functions for the Bioland module.
 *
 * Contains helper functions and update hooks for managing JSON:API resources.
 */

/**
 * Helper function to disable specific JSON:API resources.
 *
 * Disables sensitive or unnecessary JSON:API endpoints using jsonapi_extras.
 *
 * @return string
 *   Status messages about disabled resources.
 */
function _bioland_disable_jsonapi_resources() {
  // Check if jsonapi_extras module is enabled.
  if ($skip = _bioland_require_module('jsonapi_extras', 'JSON API resource disabling')) {
    return $skip;
  }

  // List of resource types to disable (format: entity_type--bundle).
  $resources_to_disable = [
    'facets_facet_source--facets_facet_source',
    'facets_facet--facets_facet',
    'jsonapi--jsonapi',
    'jsonapi--jsonapi_index',
    'language_content_settings--language_content_settings',
    'linkit_profile--linkit_profile',
    'view--view',
    'search_api_index--search_api_index',
    'search_api_task--search_api_task',
    'search_api_server--search_api_server',
    'mailer_policy--mailer_policy',
    'mailer_transport--mailer_transport',
    'user_role--user_role',
    'menu--menu',
    'user--user',
  ];

  $messages = [];
  $config_factory = \Drupal::configFactory();

  foreach ($resources_to_disable as $resource_type) {
    // Config name format: jsonapi_extras.jsonapi_resource_config.{resource_type}
    $config_id = $resource_type;
    $config_name = 'jsonapi_extras.jsonapi_resource_config.' . $config_id;

    $config = $config_factory->getEditable($config_name);

    // Check if already disabled.
    if ($config->get('disabled') === TRUE) {
      continue;
    }

    // Set the resource as disabled.
    $config->set('id', $config_id);
    $config->set('disabled', TRUE);
    $config->set('path', FALSE);
    $config->set('resourceType', $resource_type);
    $config->set('resourceFields', []);
    $config->save(TRUE);

    $messages[] = t('Disabled JSON API resource: @resource', ['@resource' => $resource_type]);
  }

  if (empty($messages)) {
    return t('All specified JSON API resources were already disabled.');
  }

  return implode("\n", $messages);
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Disable JSON:API resources.
 */
function bioland_update_9037() {
  return _bioland_disable_jsonapi_resources();
}
