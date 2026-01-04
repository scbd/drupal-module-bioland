<?php

/**
 * @file
 * DMSM (Country Data) related install and update functions.
 *
 * This file contains functions for synchronizing country data from the
 * DMSM (Data Management System for Member States) API.
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS
 * =============================================================================
 */

/**
 * Fetch and update countries from DMSM API based on hostname.
 *
 * @param string|null $hostname
 *   Optional hostname to parse. If NULL, uses current request host.
 *
 * @return string|null
 *   Status message about the operation, or NULL on success.
 */
function _bioland_update_countries_from_dmsm($hostname = null) {
  try {
    $dmsm_service = \Drupal::service('bioland.dmsm_config');
    $result = $dmsm_service->updateCountriesFromDmsm($hostname);
    
    if ($result['success']) {
      return NULL; // Success
    } else {
      return t('Failed to fetch countries from DMSM API: @message', ['@message' => $result['message']]);
    }
  }
  catch (\Exception $e) {
    return t('Error fetching countries from DMSM API: @message', ['@message' => $e->getMessage()]);
  }
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Migrate single 'country' config to 'countries' array.
 */
function bioland_update_9001() {
  $config_factory = \Drupal::configFactory();
  $editable = $config_factory->getEditable('bioland.settings');
  $old = $editable->get('country');
  $new = $editable->get('countries');
  if ($old && empty($new)) {
    $editable->set('countries', [$old]);
    $editable->clear('country');
    $editable->save();
  }
}

/**
 * Fetch and update countries from DMSM API based on hostname.
 */
function bioland_update_9023() {
  $result = _bioland_update_countries_from_dmsm();
  
  if ($result === NULL) {
    return t('Successfully updated countries from DMSM API.');
  }
  
  // Update must fail if countries could not be fetched.
  throw new \RuntimeException($result);
}

/**
 * Update region and continent from DMSM API.
 *
 * Fetches and populates region and continent config values from DMSM API
 * based on the current hostname.
 *
 * @version 1.0.36
 */
function bioland_update_9046() {
  try {
    // Use the existing helper function to update from DMSM API.
    $result = _bioland_update_countries_from_dmsm();
    
    if ($result) {
      // If there was an error message, return it.
      return $result;
    }
    
    // Get the config to check what was populated.
    $config = \Drupal::config('bioland.settings');
    $region = $config->get('region');
    $continent = $config->get('continent');
    
    $updates = [];
    if ($region) {
      $updates[] = t('region: @region', ['@region' => $region]);
    }
    if ($continent) {
      $updates[] = t('continent: @continent', ['@continent' => $continent]);
    }
    
    if (!empty($updates)) {
      return t('Successfully updated from DMSM API: @updates', ['@updates' => implode(', ', $updates)]);
    }
    
    return t('DMSM API call succeeded but region/continent not found in response.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->warning('Could not update region/continent from DMSM API: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Could not fetch region/continent from DMSM API: @message', ['@message' => $e->getMessage()]);
  }
}
