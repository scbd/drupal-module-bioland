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

/**
 * Seed bioland.settings defaults added to config/install since 1.0.47.
 *
 * config/install/bioland.settings.yml gained the home_widgets.*.enable flags
 * and mega_menu.content_types.position (BL-739) plus the BSL home-page hero
 * help text (PR #19), but existing sites never re-import that file, so the
 * keys are absent from their active config. Runtime readers fall back safely
 * (a missing enable flag is treated as enabled via `!== FALSE`, position
 * falls back to 'top'), so this seeds the same defaults new installs get to
 * keep active config aligned with config/install and the settings forms
 * backed by explicit values. Only sets keys that are missing, never
 * overwrites a saved value (idempotent). Also re-seeds bioland_update_9062()'s
 * BSL hero keys, since that hook is skipped on 9070-lineage databases (see the
 * numbering note on bioland_update_9071()).
 */
function bioland_update_9073() {
  $defaults = [
    'mega_menu.content_types.position' => 'top',
    'home_widgets.gbif_widget.enable' => TRUE,
    'home_widgets.latest_news_widget.enable' => TRUE,
    'home_widgets.national_targets_widget.enable' => TRUE,
    'home_widgets.panorama_solutions_widget.enable' => TRUE,
    'home_widgets.elearning_widget.enable' => TRUE,
    'home_widgets.implementation_widget.enable' => TRUE,
    'home_widgets.technical_cooperation_widget.enable' => TRUE,
    'home_widgets.latest_discussions_widget.enable' => TRUE,
    'home_widgets.content_statistics_widget.enable' => TRUE,
    'home_widgets.geobon_widget.enable' => TRUE,
    // Re-seed bioland_update_9062()'s keys: that hook never runs on databases
    // whose schema version was already past it (see bioland_update_9071()).
    'help_comments.home_hero_help_bsl_heading' => 'About Home Page Heroe',
    'help_comments.home_hero_help_bsl_text' => 'Heros are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you edit the hero banner for the home page.',
  ];

  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  $seeded = 0;
  foreach ($defaults as $key => $value) {
    if ($config->get($key) === NULL) {
      $config->set($key, $value);
      $seeded++;
    }
  }
  if ($seeded > 0) {
    $config->save();
  }

  return t('Seeded @count missing bioland.settings default(s) from config/install (home widgets, mega menu position, BSL hero help text).', ['@count' => $seeded]);
}
