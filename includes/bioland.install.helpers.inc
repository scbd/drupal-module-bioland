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

/**
 * Align bioland.settings with config/install: seed component menu, drop forums.
 *
 * Two active-config corrections in one save, both idempotent.
 *
 * 1. config/install/bioland.settings.yml gained component_menu_add_enabled and
 *    component_menu_show_attributes with the dedicated "Add Mega Menu
 *    component" flow, but existing sites never re-import that file, so both
 *    keys are absent from their active config. The runtime readers fall back
 *    safely (the add flow is on unless the key is explicitly FALSE, the raw
 *    Attributes fieldset stays hidden unless the key is truthy), so this seeds
 *    the same defaults a new install gets, to keep active config aligned with
 *    config/install and the admin settings form backed by explicit values.
 *    Only sets keys that are missing, never overwrites a saved value.
 * 2. theme.mega_menu.forums is retired. The Theme tab's "Show Forums in the
 *    mega menu" checkbox is gone, the key is out of BiolandThemeContract::KEYS
 *    and out of config/schema/bioland.schema.yml -- so any site that saved the
 *    Theme tab while the checkbox existed is now carrying a key its schema no
 *    longer declares, which Drupal reports as a schema violation. Cleared only
 *    where present, so a site that never authored it is untouched.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every site,
 * so per SearchApiConvergenceHookTest it must also re-apply the canonical v2
 * Search API config so the last writer never leaves a half-configured index.
 * Seeding is set-if-missing, the clear is clear-if-present, and the v2
 * converger is create-or-update / UUID-preserving, so this is safe to run
 * more than once.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 * @see \Drupal\bioland\Form\BiolandAdminSettingsForm
 * @see \Drupal\bioland\Access\BiolandComponentMenuAccessCheck
 * @see \Drupal\bioland\BiolandThemeContract
 */
function bioland_update_9079() {
  $messages = [];

  $defaults = [
    'component_menu_add_enabled' => TRUE,
    'component_menu_show_attributes' => FALSE,
  ];

  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  $seeded = [];
  foreach ($defaults as $key => $value) {
    if ($config->get($key) === NULL) {
      $config->set($key, $value);
      $seeded[] = $key;
    }
  }

  // Presence, not truthiness: an authored FALSE is exactly the value most
  // likely to be sitting there, and it still has to go.
  $clearedForums = $config->get('theme.mega_menu.forums') !== NULL;
  if ($clearedForums) {
    $config->clear('theme.mega_menu.forums');

    // Prune the parents the clear just emptied. Drupal's clear() removes the
    // leaf and leaves the containing map behind, and on a site whose only
    // authored theme value was the retired checkbox that leaves
    // `theme: {mega_menu: {}}` sitting in active config. That husk is not
    // inert. BiolandThemeForm::themeDefaults() reads presence, not
    // truthiness: `['mega_menu' => []]` is a non-empty array, so it counts as
    // authored, the dmsm seed never runs, and every colour field renders
    // empty -- the exact input core's Color::validateColor() turns into
    // #000000 on the next Save. Pruning restores the genuinely-unauthored
    // state the D2 fall-through expects. It also keeps empty maps out of
    // config generally: PHP renders an empty map and an empty list as the
    // same `[]`, an ambiguity BiolandDmsmConfigService's per-leaf theme merge
    // has to special-case explicitly (see its $isBranch note).
    if ($config->get('theme.mega_menu') === []) {
      $config->clear('theme.mega_menu');
    }
    if ($config->get('theme') === []) {
      $config->clear('theme');
    }
  }

  if ($seeded || $clearedForums) {
    $config->save();
  }

  // Plain strings, not t() - the translation system is not dependable
  // mid-update.
  if ($seeded) {
    $messages[] = 'Seeded missing component menu link setting(s): ' . implode(', ', $seeded) . '.';
  }
  else {
    $messages[] = 'Component menu link settings already present; left untouched.';
  }

  $messages[] = $clearedForums
    ? 'Removed the retired theme.mega_menu.forums setting.'
    : 'No retired theme.mega_menu.forums setting to remove.';

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Seeds the bioland.settings.google_analytics_ids setting on existing sites.
 *
 * The setting holds a site's comma-separated Google tag ID list ('' means
 * the feature is off). Fresh installs receive it from
 * config/install/bioland.settings.yml; this hook seeds the same '' default
 * into every already-installed site's active config so the key is
 * schema-declared everywhere.
 *
 * Seeding is set-if-missing (guarded on === NULL, presence, not truthiness):
 * an authored '' is a real value and must survive. The hook therefore is
 * safe to run more than once.
 *
 * It also re-imports the translation catalogs so sites with the locale
 * module gain the three new Google tag UI strings, guarded on locale being
 * enabled exactly as bioland_update_9078() does.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every
 * site, so per SearchApiConvergenceHookTest it must also re-apply the
 * canonical v2 Search API config so the last writer never leaves a
 * half-configured index.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 * @see \Drupal\bioland\Form\BiolandFrontEndGeneralForm
 */
function bioland_update_9080() {
  $messages = [];

  $config = \Drupal::configFactory()->getEditable('bioland.settings');

  // Presence, not truthiness: a saved '' is a real authored value (the
  // feature is off) and must survive a re-run.
  if ($config->get('google_analytics_ids') === NULL) {
    $config->set('google_analytics_ids', '');
    $config->save();
    $messages[] = 'Seeded missing Google tag IDs setting (google_analytics_ids).';
  }
  else {
    $messages[] = 'Google tag IDs setting already present; left untouched.';
  }

  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    $messages[] = 'Skipped translation import: locale module is not enabled. Enable the Interface Translation module and run updates again.';
  }
  else {
    _bioland_import_translations();
    $messages[] = 'Re-imported Bioland translation catalogs for the Google tag IDs strings. Check logs for details.';
  }

  // Plain strings, not t() - the translation system is not dependable
  // mid-update.

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Seeds bioland.settings.google_analytics_enabled, disabled, on every site.
 *
 * The setting is the single switch that decides whether the public site loads
 * any Google tag at all. It is FALSE on every site and every environment,
 * including sites that already have tag IDs configured: BL-1015 replaced the
 * old implicit "IDs present plus environment rules" behaviour with this
 * explicit checkbox, and an existing site must not silently keep measuring on
 * the strength of an ID that was never an opt-in.
 *
 * Fresh installs receive the same FALSE from
 * config/install/bioland.settings.yml. Seeding here is set-if-missing (guarded
 * on === NULL, presence, not truthiness) so a site that has since turned the
 * switch on does not have it turned back off by a re-run.
 *
 * It also re-imports the translation catalogs, guarded on locale being enabled
 * exactly as bioland_update_9080() does, so an existing site picks up the
 * reworded tag IDs help text. The switch's own UI strings are not in the
 * catalogs yet - they arrive with BL-1028 - so this re-import cannot
 * translate them.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every site,
 * so per SearchApiConvergenceHookTest it must also re-apply the canonical v2
 * Search API config so the last writer never leaves a half-configured index.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 * @see \Drupal\bioland\Form\BiolandFrontEndGeneralForm
 */
function bioland_update_9081() {
  // Plain strings, not t() - the translation system is not dependable
  // mid-update.
  $messages = [];

  $config = \Drupal::configFactory()->getEditable('bioland.settings');

  // Presence, not truthiness: a saved FALSE is a real authored value and an
  // authored TRUE must survive a re-run.
  if ($config->get('google_analytics_enabled') === NULL) {
    $config->set('google_analytics_enabled', FALSE);
    $config->save();
    $messages[] = 'Seeded the Google Analytics switch (google_analytics_enabled) as disabled.';
  }
  else {
    $messages[] = 'Google Analytics switch already present; left untouched.';
  }

  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    $messages[] = 'Skipped translation import: locale module is not enabled. Enable the Interface Translation module and run updates again.';
  }
  else {
    _bioland_import_translations();
    $messages[] = 'Re-imported Bioland translation catalogs. The Google Analytics switch strings arrive with BL-1028. Check logs for details.';
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
