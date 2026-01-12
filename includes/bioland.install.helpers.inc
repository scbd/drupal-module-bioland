<?php

/**
 * @file
 * Helper utilities for Bioland module installation.
 *
 * Contains shared utility functions used across all other install includes.
 */

/**
 * Check if a module is enabled, returning skip message if not.
 *
 * @param string $module
 *   The module name to check.
 * @param string $feature
 *   Description of the feature being skipped.
 *
 * @return string|null
 *   Skip message if module not enabled, NULL if module is available.
 */
function _bioland_require_module($module, $feature = 'operation') {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    return t('@module module is not enabled; skipping @feature.', [
      '@module' => $module,
      '@feature' => $feature,
    ]);
  }
  return NULL;
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * Disable system cron.
 */
function bioland_update_9061() {
  \Drupal::state()->set('system.cron_disabled', TRUE);
  return t('System cron has been disabled.');
}
