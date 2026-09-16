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
 * Queue a countries update from the config API based on hostname.
 *
 * This ENQUEUES; it never fetches. Update hooks run under `drush updatedb` on
 * the CLI but also under /update.php, which is a web request in the site's own
 * PHP-FPM pool. Now that the config API base URL is configurable and is
 * expected to point at this site's own Drupal route, an in-request fetch would
 * block one worker of that pool waiting on another worker of the same pool, so
 * enough concurrent triggers deadlock the site. The queue
 * (bioland_dmsm_geography) is drained by cron, outside the request.
 *
 * @param string|null $hostname
 *   Optional hostname to parse. If NULL, uses current request host.
 *
 * @return string|null
 *   Status message about the operation, or NULL when the item was queued.
 */
function _bioland_update_countries_from_dmsm($hostname = null) {
  try {
    $dmsm_service = \Drupal::service('bioland.dmsm_config');
    $result = $dmsm_service->enqueueCountriesUpdate($hostname);

    if ($result['success']) {
      return NULL; // Queued.
    } else {
      return t('Failed to queue the countries update: @message', ['@message' => $result['message']]);
    }
  }
  catch (\Exception $e) {
    return t('Error queueing the countries update: @message', ['@message' => $e->getMessage()]);
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
 * Queue a countries update from the config API based on hostname.
 */
function bioland_update_9023() {
  $result = _bioland_update_countries_from_dmsm();

  if ($result === NULL) {
    return t('Queued the countries update; it runs on the next cron queue pass.');
  }

  // Update must fail if the item could not be queued.
  throw new \RuntimeException($result);
}

/**
 * Queue a region and continent update from the config API.
 *
 * Queues the same geography fetch that populates region and continent, based
 * on the current hostname. The values are written by the queue worker, not by
 * this hook, so the hook cannot report the resulting values.
 *
 * @version 1.0.36
 */
function bioland_update_9046() {
  try {
    // Use the existing helper function to queue the update.
    $result = _bioland_update_countries_from_dmsm();

    if ($result) {
      // If there was an error message, return it.
      return $result;
    }

    return t('Queued the region/continent update; it runs on the next cron queue pass.');
  }
  catch (\Exception $e) {
    \Drupal::logger('bioland')->warning('Could not update region/continent from DMSM API: @message', [
      '@message' => $e->getMessage(),
    ]);
    return t('Could not fetch region/continent from DMSM API: @message', ['@message' => $e->getMessage()]);
  }
}
