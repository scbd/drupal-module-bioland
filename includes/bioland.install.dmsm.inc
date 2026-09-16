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
 * Enqueueing is deduplicated per process: hook_install and both update hooks
 * request the same fetch for the same host, and a single `drush updatedb` run
 * would otherwise queue three identical items that each repeat the same work.
 *
 * @param string|null $hostname
 *   Optional hostname to parse. If NULL, uses current request host.
 * @param bool $reset
 *   Test seam: clear the per-process dedupe record before running.
 *
 * @return string|null
 *   Status message about the operation, or NULL when the item was queued (or
 *   was already queued earlier in this process).
 */
function _bioland_update_countries_from_dmsm($hostname = NULL, $reset = FALSE) {
  $queued = &_bioland_dmsm_enqueue_dedupe_state();

  if ($reset) {
    $queued = [];
  }

  $dedupe_key = $hostname === NULL ? '__current_request__' : (string) $hostname;

  if (isset($queued[$dedupe_key])) {
    return NULL;
  }

  try {
    $dmsm_service = \Drupal::service('bioland.dmsm_config');
    $result = $dmsm_service->enqueueCountriesUpdate($hostname);

    if ($result['success']) {
      $queued[$dedupe_key] = TRUE;
      return NULL; // Queued.
    } else {
      return t('Failed to queue the countries update: @message', ['@message' => $result['message']]);
    }
  }
  catch (\Exception $e) {
    return t('Error queueing the countries update: @message', ['@message' => $e->getMessage()]);
  }
}

/**
 * The per-process record of which hosts have already been enqueued.
 *
 * Held in its own function so a test can clear it between cases without
 * enqueueing anything; a static inside the caller could only be reset by
 * calling the caller.
 *
 * @return array
 *   The dedupe record, by reference.
 */
function &_bioland_dmsm_enqueue_dedupe_state() {
  static $queued = [];

  return $queued;
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
  $result = _bioland_update_countries_from_dmsm();

  if ($result === NULL) {
    return t('Queued the region/continent update; it runs on the next cron queue pass.');
  }

  // Same semantics as bioland_update_9023: a failure to queue fails the
  // update rather than returning a message an operator may never read.
  throw new \RuntimeException($result);
}

/**
 * Seed the DMSM config base URL on sites installed before it existed.
 *
 * config/install/ is imported at module install only, so the ~211 sites that
 * were already installed never receive a new key from it. Without this hook
 * bioland.settings.dmsm_config_base_url stays unset on every one of them, the
 * fetch refuses, and geography sync stops fleet-wide on deploy - visible only
 * as a watchdog error from a cron worker.
 *
 * The value is deliberately taken from the deployment, not from a literal in
 * this file: the module ships no host, in code or in config, because a shipped
 * default would have to be the dev host that the production guard must refuse.
 * The update FAILS when it cannot derive a value, so the problem surfaces
 * during deployment rather than silently at the next cron run.
 *
 * As the highest-numbered hook it is also the last writer for every site, so
 * per SearchApiConvergenceHookTest it must converge the canonical Search API
 * v2 config. It does that FIRST, not last: the seed below can legitimately
 * fail the update, and convergence must not be skipped because a different
 * key was missing from the deployment.
 */
function bioland_update_9079() {
  $key = \Drupal\bioland\Service\BiolandDmsmConfigService::CONFIG_BASE_URL_KEY;
  $allowlist_key = \Drupal\bioland\Service\BiolandDmsmConfigService::CONFIG_PROD_HOST_ALLOWLIST_KEY;

  $messages = [];

  if (function_exists('_bioland_v2_update_search_and_facets_config')) {
    $messages[] = (string) _bioland_v2_update_search_and_facets_config();
  }

  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  $current = $config->get($key);

  // The production allowlist is deploy-time configuration for the same reason
  // the base URL is: no production host ships in code. Seed it when the
  // deployment offers one, whether or not the base URL itself needs seeding.
  $allowlist = _bioland_dmsm_prod_allowlist_from_environment();

  if ($allowlist !== NULL && !is_array($config->get($allowlist_key))) {
    $config->set($allowlist_key, $allowlist)->save();
  }

  if (is_string($current) && trim($current) !== '') {
    $messages[] = (string) t('bioland.settings.@key is already set; left unchanged.', ['@key' => $key]);

    return implode(' ', array_filter($messages));
  }

  $value = _bioland_dmsm_base_url_from_environment();

  if ($value === NULL) {
    throw new \RuntimeException(sprintf(
      'Cannot seed bioland.settings.%s: no value is available from the deployment. Set '
      . '$settings[\'bioland_dmsm_config_base_url\'] in settings.php, or the '
      . 'BIOLAND_DMSM_CONFIG_BASE_URL environment variable, to this environment\'s config API base URL '
      . '(https, no embedded credentials) and run the update again. There is no default host in code.',
      $key
    ));
  }

  $config->set($key, $value)->save();

  $messages[] = (string) t('Seeded bioland.settings.@key from the deployment environment: @value', [
    '@key' => $key,
    '@value' => $value,
  ]);

  return implode(' ', array_filter($messages));
}

/**
 * Read the config API base URL from the deployment environment.
 *
 * Order: $settings['bioland_dmsm_config_base_url'], then the
 * BIOLAND_DMSM_CONFIG_BASE_URL environment variable. The value must be an
 * absolute http(s) URL with a host and no embedded credentials; anything else
 * is treated as absent so the caller fails loudly.
 *
 * @return string|null
 *   The base URL without a trailing slash, or NULL when none is available.
 */
function _bioland_dmsm_base_url_from_environment() {
  $candidates = [
    \Drupal\Core\Site\Settings::get('bioland_dmsm_config_base_url'),
    getenv('BIOLAND_DMSM_CONFIG_BASE_URL'),
  ];

  foreach ($candidates as $candidate) {
    if (!is_string($candidate) || trim($candidate) === '') {
      continue;
    }

    $value = rtrim(trim($candidate), '/');
    $parts = parse_url($value);

    if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
      continue;
    }

    if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], TRUE)) {
      continue;
    }

    return $value;
  }

  return NULL;
}

/**
 * Read the production host allowlist from the deployment environment.
 *
 * Order: $settings['bioland_dmsm_prod_host_allowlist'] (array or
 * comma-separated string), then the comma-separated
 * BIOLAND_DMSM_PROD_HOST_ALLOWLIST environment variable.
 *
 * @return string[]|null
 *   The host names, or NULL when the deployment names none.
 */
function _bioland_dmsm_prod_allowlist_from_environment() {
  $candidates = [
    \Drupal\Core\Site\Settings::get('bioland_dmsm_prod_host_allowlist'),
    getenv('BIOLAND_DMSM_PROD_HOST_ALLOWLIST'),
  ];

  foreach ($candidates as $candidate) {
    if (is_string($candidate)) {
      $candidate = explode(',', $candidate);
    }

    if (!is_array($candidate)) {
      continue;
    }

    $hosts = [];

    foreach ($candidate as $host) {
      if (is_string($host) && trim($host) !== '') {
        $hosts[] = rtrim(strtolower(trim($host)), '.');
      }
    }

    if ($hosts !== []) {
      return array_values(array_unique($hosts));
    }
  }

  return NULL;
}

/**
 * Runtime requirements for the DMSM geography fetch.
 *
 * Called from bioland_requirements(). Without this the fetch's refusals are a
 * silent data freeze: the site keeps stale countries (or the seeded value on a
 * fresh install), the key is not in the admin form by design, and the only
 * trace is a watchdog line from a cron worker. It also carries the queue depth,
 * because per docs/adr/0004-disable-system-cron.md scheduling is external and
 * unenforced by this module - a queue that only grows is the visible symptom
 * of a cron trigger that cannot drain it.
 *
 * @param string $phase
 *   The requirements phase.
 *
 * @return array
 *   Requirements keyed as hook_requirements() expects.
 */
function _bioland_dmsm_requirements($phase) {
  if ($phase !== 'runtime') {
    return [];
  }

  $requirements = [];
  $key = \Drupal\bioland\Service\BiolandDmsmConfigService::CONFIG_BASE_URL_KEY;

  try {
    $check = \Drupal::service('bioland.dmsm_config')->checkConfiguredBaseUrl();
  }
  catch (\Throwable $e) {
    $check = ['status' => 'refused', 'message' => $e->getMessage(), 'value' => ''];
  }

  if ($check['status'] === 'ok') {
    $requirements['bioland_dmsm_base_url'] = [
      'title' => t('Bioland DMSM geography source'),
      'value' => $check['value'],
      'severity' => REQUIREMENT_OK,
    ];
  }
  else {
    $requirements['bioland_dmsm_base_url'] = [
      'title' => t('Bioland DMSM geography source'),
      'value' => $check['status'] === 'unset'
        ? t('Not configured (bioland.settings.@key)', ['@key' => $key])
        : t('Refused: @value', ['@value' => $check['value']]),
      'description' => t('@message Geography (countries, region, continent) is frozen at its current values until this is fixed.', [
        '@message' => $check['message'],
      ]),
      'severity' => REQUIREMENT_ERROR,
    ];
  }

  try {
    $queue = \Drupal::service('queue')->get(\Drupal\bioland\Service\BiolandDmsmConfigService::QUEUE_NAME);
    $pending = (int) $queue->numberOfItems();
  }
  catch (\Throwable $e) {
    $pending = 0;
  }

  if ($pending > 0) {
    $requirements['bioland_dmsm_queue'] = [
      'title' => t('Bioland DMSM geography queue'),
      'value' => t('@count item(s) pending', ['@count' => $pending]),
      'description' => t('The geography fetch runs only from the CLI (drush cron / drush queue:run): it refuses to run inside a web request. If this count does not fall to zero, this environment has no CLI cron trigger draining the queue (see docs/adr/0004-disable-system-cron.md).'),
      'severity' => REQUIREMENT_WARNING,
    ];
  }

  return $requirements;
}
