<?php

/**
 * @file
 * Translation and locale related install/update functions for the Bioland module.
 *
 * This file contains helper functions for importing translations from PO files
 * and update hooks related to translation configuration.
 */

/*
 * =============================================================================
 * HELPER FUNCTIONS - Translation and Locale Utilities
 * =============================================================================
 */

/**
 * Import translations from PO files in the module's translations directory.
 *
 * Scans the bioland/translations directory for .po files and imports them
 * into the locale database. Only imports for enabled languages.
 *
 * Uses \Drupal\locale\Gettext::fileToDatabase() - the supported programmatic
 * PO import API (PSR-4 autoloaded). A previous implementation called a non-existent
 * "locale.translation" service (with an "importTranslationFile()" method that
 * does not exist), so its availability guard short-circuited and NOTHING was
 * ever imported: the re-import update hooks reported success while silently
 * importing zero strings, which is why newer msgids (e.g. the BSL home-page
 * hero help text) rendered untranslated on every locale.
 */
function _bioland_import_translations() {
  // Only import if locale module is enabled.
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    \Drupal::logger('bioland')->notice('Skipping translation import: locale module is not enabled.');
    return;
  }

  $module_path = \Drupal::service('extension.list.module')->getPath('bioland');
  $translations_path = $module_path . '/translations';

  // Check if translations directory exists.
  if (!is_dir($translations_path)) {
    \Drupal::logger('bioland')->notice('Skipping translation import: translations directory not found.');
    return;
  }

  // Get list of PO files.
  $files = \Drupal::service('file_system')->scanDirectory($translations_path, '/\.po$/');

  if (empty($files)) {
    \Drupal::logger('bioland')->notice('No translation files found to import.');
    return;
  }

  $imported_count = 0;
  $skipped_count = 0;

  foreach ($files as $file) {
    // Extract language code from filename (e.g., bioland.fr.po -> fr,
    // bioland.zh-hans.po -> zh-hans). Supports 2-3 letter base codes with an
    // optional hyphenated subtag (zh-hans, zh-hant, pt-br) so multi-part Drupal
    // langcodes are not silently skipped by a [a-z]{2}-only pattern.
    if (preg_match('/bioland\.([a-z]{2,3}(?:-[a-z0-9]+)?)\.po$/', $file->filename, $matches)) {
      $langcode = $matches[1];

      // Check if language is enabled.
      $language = \Drupal::languageManager()->getLanguage($langcode);
      if (!$language) {
        \Drupal::logger('bioland')->notice('Skipping translation file @file: language @lang is not enabled.', [
          '@file' => $file->filename,
          '@lang' => $langcode,
        ]);
        $skipped_count++;
        continue;
      }

      // Import the PO file.
      try {
        $options = [
          'langcode' => $langcode,
          'overwrite_options' => [
            'customized' => TRUE,
            'not_customized' => TRUE,
          ],
          'customized' => TRUE,
        ];

        // $file already exposes ->filename and ->uri (from scanDirectory());
        // fileToDatabase() also reads ->langcode.
        $file->langcode = $langcode;
        \Drupal\locale\Gettext::fileToDatabase($file, $options);
        $imported_count++;
        \Drupal::logger('bioland')->notice('Successfully imported translation file @file.', [
          '@file' => $file->filename,
        ]);
      }
      catch (\Exception $e) {
        \Drupal::logger('bioland')->error('Failed to import translation file @file: @message', [
          '@file' => $file->filename,
          '@message' => $e->getMessage(),
        ]);
        $skipped_count++;
      }
    }
  }

  if ($imported_count > 0) {
    // Invalidate the persistent locale cache (tagged 'locale' by LocaleLookup)
    // so freshly imported strings are served on the next request instead of a
    // stale cached translation set.
    \Drupal\Core\Cache\Cache::invalidateTags(['locale']);
    \Drupal::logger('bioland')->notice('Imported @count translation file(s).', [
      '@count' => $imported_count,
    ]);
  }
  if ($skipped_count > 0) {
    \Drupal::logger('bioland')->notice('Skipped @count translation file(s).', [
      '@count' => $skipped_count,
    ]);
  }
}

/*
 * =============================================================================
 * UPDATE HOOKS - Translation Related
 * =============================================================================
 */

/**
 * Import Bioland translation files.
 */
function bioland_update_9002() {
  // Check if locale module and service are available.
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    return t('Skipped translation import: locale module is not enabled.');
  }
  
  if (!\Drupal::hasService('locale.translation')) {
    return t('Skipped translation import: locale.translation service is not available. Enable the Interface Translation module.');
  }

  _bioland_import_translations();
  return t('Bioland translation import process completed. Check logs for details.');
}

/**
 * Set default entity type (node) for translation defaults if not configured.
 */
function bioland_update_9003() {
  $config_factory = \Drupal::configFactory();
  $editable = $config_factory->getEditable('bioland.settings');
  
  // Check if entity_types is empty or not set
  $entity_types = $editable->get('translation.entity_types');
  if (empty($entity_types)) {
    // Set node as the default entity type for translation defaults
    $editable->set('translation.entity_types', ['node']);
    $editable->save();
    return t('Set "node" as default entity type for translation defaults. Translation defaults will now be created automatically for nodes.');
  }
  
  return t('Entity types for translation defaults already configured.');
}

/**
 * Create translation defaults for all existing content nodes.
 */
function bioland_update_9012(&$sandbox) {
  // Check if translation defaults are enabled.
  $config = \Drupal::config('bioland.settings');
  if (!$config->get('translation.auto_create_enabled')) {
    return t('Translation auto-create is not enabled; skipping batch processing.');
  }

  // Initialize sandbox on first run.
  if (!isset($sandbox['progress'])) {
    // Get all content node IDs.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'content')
      ->accessCheck(FALSE);
    $sandbox['nids'] = $query->execute();
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['progress'] = 0;
    $sandbox['created'] = 0;
    $sandbox['errors'] = 0;

    if ($sandbox['total'] === 0) {
      $sandbox['#finished'] = 1;
      return t('No content nodes found to process.');
    }
  }

  // Process nodes in batches of 20.
  $batch_size = 20;
  $nids_to_process = array_slice($sandbox['nids'], $sandbox['progress'], $batch_size);

  $translation_manager = \Drupal::service('bioland.translation_manager');
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  foreach ($nids_to_process as $nid) {
    try {
      $node = $storage->load($nid);
      if ($node) {
        $created = $translation_manager->createTranslations($node, 'batch');
        if ($created) {
          $sandbox['created']++;
        }
      }
    }
    catch (\Exception $e) {
      $sandbox['errors']++;
      \Drupal::logger('bioland')->error('Update 9012: Error processing node @nid: @message', [
        '@nid' => $nid,
        '@message' => $e->getMessage(),
      ]);
    }
    $sandbox['progress']++;
  }

  // Calculate progress.
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];

  // Return status message.
  if ($sandbox['#finished'] >= 1) {
    return t('Processed @total content nodes. Created @created translation defaults. Errors: @errors.', [
      '@total' => $sandbox['total'],
      '@created' => $sandbox['created'],
      '@errors' => $sandbox['errors'],
    ]);
  }

  return t('Processed @progress of @total content nodes...', [
    '@progress' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}

/**
 * Add Biosafety Land home-page hero help text config and re-import PO files.
 *
 * Existing sites do not re-import config/install/bioland.settings.yml, so the
 * two new help_comments.home_hero_help_bsl_* properties must be seeded here.
 * Only sets each property when it is not already present (idempotent). Also
 * re-imports the translation catalogs so the new BSL msgids are available for
 * the site's installed languages.
 *
 * @see \Drupal\bioland\Form\BiolandHomePageForm::buildHeroDescriptionMarkup()
 */
function bioland_update_9062() {
  $messages = [];

  // Seed the new BSL home-page hero help text config properties if missing.
  $config = \Drupal::configFactory()->getEditable('bioland.settings');
  $changed = FALSE;

  $defaults = [
    'help_comments.home_hero_help_bsl_heading' => 'About Home Page Heroe',
    'help_comments.home_hero_help_bsl_text' => 'Heros are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you edit the hero banner for the home page.',
  ];
  foreach ($defaults as $key => $value) {
    if ($config->get($key) === NULL) {
      $config->set($key, $value);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $config->save();
    $messages[] = t('Seeded Biosafety Land home-page hero help text configuration.');
  }
  else {
    $messages[] = t('Biosafety Land home-page hero help text configuration already present.');
  }

  // Re-import translation catalogs so the new msgids are available.
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    $messages[] = t('Skipped translation import: locale module is not enabled.');
  }
  elseif (!\Drupal::hasService('locale.translation')) {
    $messages[] = t('Skipped translation import: locale.translation service is not available. Enable the Interface Translation module.');
  }
  else {
    _bioland_import_translations();
    $messages[] = t('Re-imported Bioland translations for installed languages. Check logs for details.');
  }

  return implode(' ', array_map('strval', $messages));
}

/**
 * Re-import Bioland translation files to pick up corrected/added UI strings.
 *
 * Several admin UI strings (route titles, local task titles, menu link
 * descriptions, and a handful of form labels) were missing msgid/msgstr
 * entries in translations/bioland.<langcode>.po, or had placeholder
 * msgstr values identical to the English source, so they always rendered
 * in English regardless of the active site language. The .po files have
 * been corrected; this re-runs the same import used at install time
 * (bioland_update_9002()) so existing sites pick up the fix. The importer
 * always overwrites both customized and non-customized translations
 * (see _bioland_import_translations()), so this is safe to run more than
 * once.
 */
function bioland_update_9063() {
  // Check if locale module and service are available.
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    return t('Skipped translation import: locale module is not enabled.');
  }

  if (!\Drupal::hasService('locale.translation')) {
    return t('Skipped translation import: locale.translation service is not available. Enable the Interface Translation module.');
  }

  _bioland_import_translations();
  return t('Bioland translation re-import process completed. Check logs for details.');
}

/**
 * Re-import translations for the branded config-menu description msgid.
 *
 * The admin config menu link description is built dynamically by
 * \Drupal\bioland\Plugin\Derivative\BiolandMenuLink as
 * t('Configure @branding settings including locales, countries, and region.',
 * ['@branding' => 'Biosafety Land' | 'Bioland']). That placeholder-based msgid
 * had no msgstr in any translations/bioland.<langcode>.po catalog (only the
 * older, now-orphaned 'Configure settings including locales, countries, and
 * region.' msgid was present), so the description always rendered in English.
 * The branded msgid has been added to every catalog; this re-runs the same
 * importer used at install time so existing sites pick up the new strings.
 * The importer overwrites both customized and non-customized translations
 * (see _bioland_import_translations()), so this is safe to run more than once.
 *
 * Because this is now the highest-numbered update hook, it must also re-apply
 * the canonical Search API v2 configuration LAST, preserving the convergence
 * invariant previously held by bioland_update_9064() (see
 * \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest and
 * _bioland_v2_update_search_and_facets_config()).
 */
function bioland_update_9065() {
  $messages = [];

  // Re-import translation catalogs so the branded msgid becomes available.
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    $messages[] = t('Skipped translation import: locale module is not enabled.');
  }
  elseif (!\Drupal::hasService('locale.translation')) {
    $messages[] = t('Skipped translation import: locale.translation service is not available. Enable the Interface Translation module.');
  }
  else {
    _bioland_import_translations();
    $messages[] = t('Bioland branded config-menu description re-import completed. Check logs for details.');
  }

  // Converge the canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Re-import PO catalogs with the working importer (repairs the broken guard).
 *
 * NOTE ON NUMBERING (applies to hooks 9071-9074): the harness/staging database
 * lineage carries a bioland schema version of 9070, recorded by experimental
 * code that was never committed (no bioland_update_9066-9070 exists in this
 * repo). Drupal only runs hooks numbered ABOVE the stored schema version, so
 * on those databases every hook <= 9070 - including the earlier translation
 * re-imports 9062/9063/9065 - is permanently skipped and `drush updatedb`
 * reports nothing pending. All corrective hooks therefore start at 9071.
 *
 * This one exists because hooks 9062/9063/9065 have a second, independent
 * defect: each guards the import on \Drupal::hasService('locale.translation'),
 * a service that does NOT exist in Drupal core, so even when they ran they hit
 * the guard and imported zero strings. That is why UI strings added to the .po
 * catalogs (e.g. the branded "Configure @branding settings ..." admin-menu
 * description) never reached locales_target and still render in English.
 *
 * _bioland_import_translations() already uses the supported
 * \Drupal\locale\Gettext::fileToDatabase() API and invalidates the 'locale'
 * cache tag. This hook simply calls it guarded ONLY on the locale module being
 * enabled - no phantom-service check - so the import actually happens. The
 * importer overwrites customized and non-customized strings alike, so it is
 * safe to run more than once.
 */
function bioland_update_9071() {
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    return t('Skipped translation import: locale module is not enabled. Enable the Interface Translation module and run updates again.');
  }

  _bioland_import_translations();
  return t('Re-imported Bioland translation catalogs with the corrected importer. Check logs for details.');
}

/**
 * Re-import PO catalogs for the 1.1.2 translation corrections and converge.
 *
 * The 1.1.2 release corrected the Spanish (and all other) "Front End" /
 * "Front End General" admin-UI strings from lowercased/machine-translated
 * English to proper localized "interface" terms, and added a dedicated
 * translations/bioland.zh-hans.po catalog (previously the Chinese strings
 * lived only in bioland.zh.po, which the site's zh-hans langcode never
 * matched). Because Drupal runs each numbered update hook only once, sites
 * that already ran the earlier 9071 re-import will not pick these corrected
 * catalogs up without a NEW higher-numbered hook - this is it.
 *
 * As the highest-numbered hook it is guaranteed to run LAST for every site, so
 * per SearchApiConvergenceHookTest it must also re-apply the canonical v2
 * Search API config (via _bioland_v2_update_search_and_facets_config(), loaded
 * by bioland.install) so the last writer never leaves a half-configured index.
 * The importer overwrites customized and non-customized strings alike, and the
 * v2 converger is create-or-update / UUID-preserving, so this is safe to run
 * more than once.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9075() {
  $messages = [];

  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    $messages[] = t('Skipped translation import: locale module is not enabled. Enable the Interface Translation module and run updates again.');
  }
  else {
    _bioland_import_translations();
    $messages[] = t('Re-imported Bioland 1.1.2 translation catalogs (interface strings + zh-hans). Check logs for details.');
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Re-import translation catalogs for the BSL home-widget section strings.
 *
 * The BSL-only home-page widget section (National Biosafety Framework, BCH
 * News, BCH Resources) added in BL-823 introduced ten new admin-UI msgids that
 * predated every .po catalog, so those labels rendered untranslated on the
 * home-widgets settings form (e.g. Spanish). This hook re-imports the catalogs
 * now that those msgids exist (real translations for ar/en/es/fr/ru/zh, English
 * fallback elsewhere). Because Drupal runs each numbered update hook only once,
 * sites that already ran 9075 need this NEW higher-numbered hook to pick them up.
 *
 * As the new highest-numbered hook it is guaranteed to run LAST on every site,
 * so per SearchApiConvergenceHookTest it must also re-apply the canonical v2
 * Search API config so the last writer never leaves a half-configured index.
 * The importer overwrites customized and non-customized strings alike, and the
 * v2 converger is create-or-update / UUID-preserving, so this is safe to run
 * more than once.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9076() {
  $messages = [];

  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    $messages[] = t('Skipped translation import: locale module is not enabled. Enable the Interface Translation module and run updates again.');
  }
  else {
    _bioland_import_translations();
    $messages[] = t('Re-imported Bioland translation catalogs for the BSL home-widget section strings. Check logs for details.');
  }

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
