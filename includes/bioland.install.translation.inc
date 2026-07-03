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
    // Extract language code from filename (e.g., bioland.fr.po -> fr).
    if (preg_match('/bioland\.([a-z]{2})\.po$/', $file->filename, $matches)) {
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
 * Re-import translations after fixing the broken PO importer.
 *
 * Updates 9062/9063 called _bioland_import_translations(), but that helper (and
 * the hooks' own guards) depended on a "locale.translation" service that does
 * not exist in Drupal core, so the availability check short-circuited and no
 * strings were ever imported - the hooks reported success while doing nothing.
 * The helper now uses \Drupal\locale\Gettext::fileToDatabase() and invalidates
 * the 'locale' cache. This re-runs the import so existing sites finally pick up
 * the missing translations (e.g. the BSL home-page hero help text). Safe to run
 * more than once - the importer overwrites both customized and non-customized
 * translations.
 */
function bioland_update_9065() {
  if (!\Drupal::moduleHandler()->moduleExists('locale')) {
    return t('Skipped translation import: locale module is not enabled. Enable the Interface Translation module and run updates again.');
  }

  _bioland_import_translations();
  return t('Re-imported Bioland translations with the corrected PO importer. Check logs for details.');
}
