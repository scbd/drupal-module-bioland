<?php

/**
 * @file
 * Editable home page (BL-838): node 1000 as the site front page.
 *
 * Ports the per-site steps of the dmsm fix script
 * server/efs/scripts/multi-site/fixes/set-home-page-editable/ into the module
 * so a single site gets the same result on hook_install() and on
 * bioland_update_9100(). The script writes SQL because it runs against
 * hundreds of site databases from outside Drupal; here the entity API is used.
 *
 * The node identity (nid, uuid, bundle, title, source langcode) must match the
 * script exactly and never change: a site converted by the script and then
 * updated by the module (or the reverse) must end with the same node.
 */

/**
 * Fixed nid of the editable home node, shared with the dmsm script.
 */
const BIOLAND_HOME_NODE_NID = 1000;

/**
 * Fixed uuid of the editable home node (dmsm HOME_UUID). Never change.
 */
const BIOLAND_HOME_NODE_UUID = 'a90ccf69-d2ca-4031-949f-adc0941e266f';

/**
 * Bundle, title and source language of the home node.
 */
const BIOLAND_HOME_NODE_BUNDLE = 'content';
const BIOLAND_HOME_NODE_TITLE = 'Home';
const BIOLAND_HOME_NODE_LANGCODE = 'en';

/**
 * The "Basic Page" placement tag (vocabulary tags) on bl/bl2, pinned by uuid.
 */
const BIOLAND_HOME_NODE_BASIC_PAGE_TAG_UUID = '42e5b98b-85de-4d3d-8103-36f54349dc3b';

/**
 * The placement tag name on Biosafety Land sites; must match exactly one term.
 */
const BIOLAND_HOME_NODE_BSL_TAG_NAME = 'Article';

/**
 * The legacy home: the system_pages "Home" term, /taxonomy/term/20.
 */
const BIOLAND_HOME_NODE_LEGACY_TID = 20;

/*
 * =============================================================================
 * PURE HELPERS
 * =============================================================================
 */

/**
 * Langcodes that never get a translation of the home node.
 *
 * Same set as dmsm libs/locale.mjs EXCLUDED_LANGCODES.
 *
 * @return string[]
 *   The skipped langcodes.
 */
function _bioland_home_node_skipped_langcodes() {
  return ['en', 'xx-lolspeak', 'zxx', 'und', 'mul'];
}

/**
 * Map a Drupal langcode to the head's i18n locale code.
 *
 * Mirrors dmsm libs/locale.mjs mapLocaleFromDrupal().
 *
 * @param string $langcode
 *   The Drupal langcode.
 *
 * @return string
 *   The head locale code.
 */
function _bioland_home_node_locale_from_drupal($langcode) {
  $map = ['zh-hans' => 'zh', 'fil' => 'tl', 'xx-lolspeak' => 'xx'];
  return $map[$langcode] ?? $langcode;
}

/**
 * Translated "Home" titles keyed by head locale code.
 *
 * Generated from bioland-head i18n/locales/<locale>.json key "Home" (80 of
 * the 82 locale files have it; no and ps do not, en is the source). These are
 * the head's own UI labels, which are literal (de Heim, es Hogar); editors can
 * correct the title per site. Regenerate from the head rather than editing by
 * hand, and never from a translation service.
 *
 * @return array<string, string>
 *   Locale code => title.
 */
function _bioland_home_node_title_map() {
  return [
    'af' => 'Tuis',
    'am' => 'ቤት',
    'ar' => 'بيت',
    'az' => 'Ev',
    'be' => 'дадому',
    'bg' => 'У дома',
    'bn' => 'বাড়ি',
    'bs' => 'Dom',
    'ca' => 'a casa',
    'cs' => 'Domov',
    'cy' => 'Cartref',
    'da' => 'Hjem',
    'de' => 'Heim',
    'dz' => 'ཁྱིམ',
    'el' => 'Σπίτι',
    'eo' => 'Hejmo',
    'es' => 'Hogar',
    'et' => 'Kodu',
    'eu' => 'Hasiera',
    'fa' => 'صفحه اصلی',
    'fi' => 'Koti',
    'fr' => 'Accueil',
    'fy' => 'Thús',
    'ga' => 'Baile',
    'gd' => 'Dhachaigh',
    'gl' => 'Casa',
    'gu' => 'ઘર',
    'he' => 'בית',
    'hi' => 'घर',
    'hr' => 'Dom',
    'ht' => 'Lakay',
    'hu' => 'itthon',
    'hy' => 'տուն',
    'id' => 'Rumah',
    'is' => 'Heim',
    'it' => 'Casa',
    'ja' => '家',
    'jv' => 'Ngarep',
    'ka' => 'მთავარი',
    'kk' => 'Үй',
    'km' => 'ផ្ទះ',
    'kn' => 'ಮನೆ',
    'ko' => '집',
    'ku' => 'Xane',
    'ky' => 'Үй',
    'lo' => 'ບ້ານ',
    'lt' => 'Namai',
    'lv' => 'Mājas',
    'mg' => 'an-trano',
    'mk' => 'Дома',
    'ml' => 'വീട്',
    'mn' => 'Гэр',
    'mr' => 'मुख्यपृष्ठ',
    'ms' => 'Rumah',
    'my' => 'အိမ်',
    'ne' => 'घर',
    'nl' => 'Thuis',
    'oc' => 'Ostal',
    'pa' => 'ਘਰ',
    'pl' => 'Dom',
    'pt' => 'Lar',
    'ro' => 'Acasă',
    'ru' => 'Дом',
    'si' => 'ගෙදර',
    'sk' => 'Domov',
    'sl' => 'domov',
    'sq' => 'Shtëpi',
    'sr' => 'Кућа',
    'sv' => 'Hem',
    'ta' => 'வீடு',
    'te' => 'హోమ్',
    'th' => 'บ้าน',
    'tl' => 'Bahay',
    'tr' => 'Ev',
    'ug' => 'ئۆي',
    'uk' => 'додому',
    'ur' => 'گھر',
    'vi' => 'Trang chủ',
    'zh' => '主页',
  ];
}

/**
 * The translated home title for a Drupal langcode.
 *
 * @param string $langcode
 *   The Drupal langcode.
 *
 * @return string|null
 *   The title, or NULL when the map has none.
 */
function _bioland_home_node_title_for($langcode) {
  $map = _bioland_home_node_title_map();
  return $map[_bioland_home_node_locale_from_drupal($langcode)] ?? NULL;
}

/*
 * =============================================================================
 * INSTALL / UPDATE HELPER
 * =============================================================================
 */

/**
 * Create or complete the editable home node and make it the front page.
 *
 * Shared by hook_install() and bioland_update_9100(). Idempotent: when node
 * 1000 already has every piece, nothing is saved. Never throws: an update hook
 * that throws blocks every later update, so failures and conflicts are logged
 * and returned as the message.
 *
 * @return string
 *   A plain-string summary (not t(): translation is not dependable
 *   mid-update).
 */
function _bioland_ensure_home_node() {
  $logger = \Drupal::logger('bioland');
  $messages = [];

  try {
    $entity_type_manager = \Drupal::entityTypeManager();

    if (!$entity_type_manager->getStorage('node_type')->load(BIOLAND_HOME_NODE_BUNDLE)) {
      $message = 'Home node: skipped, the "content" content type does not exist.';
      $logger->error($message);
      return $message;
    }

    $node_storage = $entity_type_manager->getStorage('node');

    // Identity check first. A conflict leaves the site untouched.
    $node = $node_storage->load(BIOLAND_HOME_NODE_NID);
    $by_uuid = $node_storage->loadByProperties(['uuid' => BIOLAND_HOME_NODE_UUID]);
    $conflict = _bioland_home_node_identity_conflict($node, $by_uuid);
    if ($conflict !== NULL) {
      $logger->error('Home node: @conflict', ['@conflict' => $conflict]);
      return 'Home node: ' . $conflict;
    }

    // Before anything resolves /node/1000 (the attachment source, the front
    // page), so a bl1 leftover alias cannot redirect it to another node.
    $aliases_deleted = _bioland_home_node_delete_hijacking_aliases($messages);

    $system_site = \Drupal::configFactory()->getEditable('system.site');
    // The previous front page is read before anything changes it.
    $previous_front = (string) $system_site->get('page.front');

    $changed = FALSE;
    if (!$node) {
      $node = $node_storage->create([
        'nid' => BIOLAND_HOME_NODE_NID,
        'uuid' => BIOLAND_HOME_NODE_UUID,
        'type' => BIOLAND_HOME_NODE_BUNDLE,
        'title' => BIOLAND_HOME_NODE_TITLE,
        'langcode' => BIOLAND_HOME_NODE_LANGCODE,
        'status' => 1,
        'uid' => 1,
        'promote' => 0,
        'sticky' => 0,
      ]);
      // An explicit nid makes isNew() FALSE, which would save as an UPDATE of
      // a row that does not exist.
      $node->enforceIsNew(TRUE);
      $changed = TRUE;
      $messages[] = 'Created node ' . BIOLAND_HOME_NODE_NID . '.';
    }

    $changed = _bioland_home_node_fill_placement($node, $messages) || $changed;
    $changed = _bioland_home_node_fill_attachments($node, $previous_front, $messages) || $changed;
    $changed = _bioland_home_node_fill_translations($node, $messages) || $changed;

    if ($changed) {
      $node->save();
    }

    $front = '/node/' . BIOLAND_HOME_NODE_NID;
    if ($previous_front !== $front) {
      $system_site->set('page.front', $front)->save();
      $messages[] = 'Front page changed from "' . $previous_front . '" to ' . $front . '.';
    }

    if (!$changed && $previous_front === $front && !$aliases_deleted) {
      $messages[] = 'Home node ' . BIOLAND_HOME_NODE_NID . ' already complete; nothing changed.';
    }

    foreach ($messages as $message) {
      $logger->notice('Home node: @message', ['@message' => $message]);
    }
    return 'Home node: ' . implode(' ', $messages);
  }
  catch (\Throwable $e) {
    $logger->error('Home node: failed: @message', ['@message' => $e->getMessage()]);
    return 'Home node: failed, nothing further changed: ' . $e->getMessage() . ' ' . implode(' ', $messages);
  }
}

/**
 * Describe an identity conflict, or NULL when there is none.
 *
 * @param object|null $node
 *   Node 1000, if it exists.
 * @param object[] $by_uuid
 *   Nodes carrying the home uuid.
 *
 * @return string|null
 *   The conflict, naming both identities.
 */
function _bioland_home_node_identity_conflict($node, array $by_uuid) {
  if ($node && $node->uuid() !== BIOLAND_HOME_NODE_UUID) {
    return sprintf('node %d has uuid %s, expected %s; nothing changed.', BIOLAND_HOME_NODE_NID, $node->uuid(), BIOLAND_HOME_NODE_UUID);
  }
  foreach ($by_uuid as $other) {
    if ((int) $other->id() !== BIOLAND_HOME_NODE_NID) {
      return sprintf('uuid %s is on node %d, not node %d; nothing changed.', BIOLAND_HOME_NODE_UUID, $other->id(), BIOLAND_HOME_NODE_NID);
    }
  }
  return NULL;
}

/**
 * Delete path aliases that hijack /node/1000 (dmsm step 3).
 *
 * The bl1 -> bl2 migration kept old /node/<nid> links alive as aliases, so
 * some sites hold alias "/node/1000" -> "/node/11000". Inbound path processing
 * resolves the front page to /node/1000 and then that alias, so the site would
 * render the other node. Same rule as the dmsm script: alias "/node/1000" in
 * any langcode whose path is anything but "/node/1000". Runs on every call, so
 * a re-run removes an alias created since.
 *
 * @param string[] $messages
 *   Collected messages.
 *
 * @return int
 *   The number of aliases deleted.
 */
function _bioland_home_node_delete_hijacking_aliases(array &$messages) {
  $entity_type_manager = \Drupal::entityTypeManager();
  if (!$entity_type_manager->hasDefinition('path_alias')) {
    return 0;
  }

  $front = '/node/' . BIOLAND_HOME_NODE_NID;
  $storage = $entity_type_manager->getStorage('path_alias');
  $hijacking = array_filter(
    $storage->loadByProperties(['alias' => $front]),
    static fn($alias) => $alias->getPath() !== $front
  );
  if (!$hijacking) {
    return 0;
  }

  $logger = \Drupal::logger('bioland');
  foreach ($hijacking as $alias) {
    $logger->warning('Home node: deleting path_alias @id (@langcode) "@alias" -> "@path".', [
      '@id' => $alias->id(),
      '@langcode' => $alias->language()->getId(),
      '@alias' => $front,
      '@path' => $alias->getPath(),
    ]);
    $messages[] = sprintf('Deleted path_alias %s (%s) "%s" -> "%s".', $alias->id(), $alias->language()->getId(), $front, $alias->getPath());
  }
  $storage->delete($hijacking);

  return count($hijacking);
}

/**
 * Set the placement tag when the node has none.
 *
 * @param object $node
 *   The home node.
 * @param string[] $messages
 *   Collected messages.
 *
 * @return bool
 *   TRUE when the node changed.
 */
function _bioland_home_node_fill_placement($node, array &$messages) {
  $field = 'field_type_placement';
  if (!$node->hasField($field)) {
    $messages[] = 'Warning: content has no ' . $field . '; placement tag skipped.';
    return FALSE;
  }
  if (!$node->get($field)->isEmpty()) {
    return FALSE;
  }

  $is_bsl = (bool) \Drupal::config('bioland.settings')->get('is_biosafety_land');
  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

  if ($is_bsl) {
    $label = 'tags "' . BIOLAND_HOME_NODE_BSL_TAG_NAME . '"';
    $candidates = $term_storage->loadByProperties(['vid' => 'tags', 'name' => BIOLAND_HOME_NODE_BSL_TAG_NAME]);
    // The name query is collation-insensitive; require the exact name.
    $candidates = array_filter($candidates, static fn($term) => $term->label() === BIOLAND_HOME_NODE_BSL_TAG_NAME);
  }
  else {
    $label = 'tags uuid ' . BIOLAND_HOME_NODE_BASIC_PAGE_TAG_UUID;
    $candidates = $term_storage->loadByProperties(['vid' => 'tags', 'uuid' => BIOLAND_HOME_NODE_BASIC_PAGE_TAG_UUID]);
  }

  if (count($candidates) !== 1) {
    $messages[] = sprintf('Warning: %d terms match %s; placement tag skipped.', count($candidates), $label);
    return FALSE;
  }

  $term = reset($candidates);
  $node->set($field, [['target_id' => $term->id()]]);
  $messages[] = 'Set placement tag ' . $label . ' (term ' . $term->id() . ').';
  return TRUE;
}

/**
 * Resolve the entity whose hero attachments the home node inherits.
 *
 * The previous front page (alias resolved to its system path); when that is
 * already the home node, the system_pages "Home" term.
 *
 * @param string $previous_front
 *   system.site page.front before this run.
 * @param string[] $messages
 *   Collected messages.
 *
 * @return object|null
 *   The source entity, or NULL.
 */
function _bioland_home_node_attachment_source($previous_front, array &$messages) {
  $path = $previous_front;
  if ($path !== '' && \Drupal::hasService('path_alias.manager')) {
    $path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
  }

  $entity_type_manager = \Drupal::entityTypeManager();

  if ($path === '/node/' . BIOLAND_HOME_NODE_NID) {
    $term_storage = $entity_type_manager->getStorage('taxonomy_term');
    $term = $term_storage->load(BIOLAND_HOME_NODE_LEGACY_TID);
    if ($term && $term->bundle() === 'system_pages') {
      return $term;
    }
    $terms = $term_storage->loadByProperties(['vid' => 'system_pages', 'name' => BIOLAND_HOME_NODE_TITLE]);
    if (count($terms) === 1) {
      return reset($terms);
    }
    $messages[] = 'Warning: no single system_pages "Home" term; no attachments copied.';
    return NULL;
  }

  if (preg_match('#^/taxonomy/term/(\d+)$#', $path, $m)) {
    $source = $entity_type_manager->getStorage('taxonomy_term')->load((int) $m[1]);
  }
  elseif (preg_match('#^/node/(\d+)$#', $path, $m)) {
    $source = $entity_type_manager->getStorage('node')->load((int) $m[1]);
  }
  else {
    $messages[] = 'Warning: previous front page "' . $previous_front . '" is not a node or term; no attachments copied.';
    return NULL;
  }

  if (!$source) {
    $messages[] = 'Warning: previous front page "' . $path . '" does not exist; no attachments copied.';
  }
  return $source ?: NULL;
}

/**
 * Copy the previous front page's hero attachments when the node has none.
 *
 * Same order; references to deleted media are dropped with a warning. Skipped
 * when the node already has attachments, so a re-run never duplicates them or
 * undoes an editor's change.
 *
 * @param object $node
 *   The home node.
 * @param string $previous_front
 *   system.site page.front before this run.
 * @param string[] $messages
 *   Collected messages.
 *
 * @return bool
 *   TRUE when the node changed.
 */
function _bioland_home_node_fill_attachments($node, $previous_front, array &$messages) {
  $field = 'field_attachments';
  if (!$node->hasField($field)) {
    $messages[] = 'Warning: content has no ' . $field . '; attachments skipped.';
    return FALSE;
  }
  if (!$node->get($field)->isEmpty()) {
    return FALSE;
  }

  $source = _bioland_home_node_attachment_source($previous_front, $messages);
  if (!$source || !$source->hasField($field) || $source->get($field)->isEmpty()) {
    return FALSE;
  }

  $ids = [];
  foreach ($source->get($field)->getValue() as $item) {
    if (isset($item['target_id'])) {
      $ids[] = (int) $item['target_id'];
    }
  }
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadMultiple($ids);

  $kept = [];
  $dropped = [];
  foreach ($ids as $id) {
    if (isset($existing[$id])) {
      $kept[] = ['target_id' => $id];
    }
    else {
      $dropped[] = $id;
    }
  }

  if ($dropped) {
    $messages[] = 'Warning: dropped attachment reference(s) to deleted media ' . implode(', ', $dropped) . '.';
  }
  if (!$kept) {
    return FALSE;
  }

  $node->set($field, $kept);
  $messages[] = 'Copied ' . count($kept) . ' attachment(s) from the previous front page.';
  return TRUE;
}

/**
 * Add one translation per installed language that has none yet.
 *
 * @param object $node
 *   The home node.
 * @param string[] $messages
 *   Collected messages.
 *
 * @return bool
 *   TRUE when the node changed.
 */
function _bioland_home_node_fill_translations($node, array &$messages) {
  if (!\Drupal::hasService('language_manager')) {
    return FALSE;
  }

  $skipped = _bioland_home_node_skipped_langcodes();
  $missing = array_filter(
    array_keys(\Drupal::service('language_manager')->getLanguages()),
    static fn($langcode) => !in_array($langcode, $skipped, TRUE) && !$node->hasTranslation($langcode)
  );
  if (!$missing) {
    return FALSE;
  }

  // addTranslation() throws on an untranslatable bundle; that must not abort
  // the node and the front page.
  if (!$node->isTranslatable()) {
    $messages[] = 'Warning: content is not translatable; translation(s) skipped: ' . implode(', ', $missing) . '.';
    return FALSE;
  }

  $metadata = \Drupal::hasService('content_translation.manager') ? \Drupal::service('content_translation.manager') : NULL;
  $added = [];

  foreach ($missing as $langcode) {
    $title = _bioland_home_node_title_for($langcode);
    if ($title === NULL) {
      $messages[] = 'Warning: no "Home" title for ' . $langcode . '; translation skipped.';
      continue;
    }

    // uid is translatable and would otherwise default to the current user
    // (anonymous under drush updb); dmsm mirrors the source row's owner.
    $translation = $node->addTranslation($langcode, [
      'title' => $title,
      'status' => 1,
      'uid' => $node->getOwnerId(),
    ]);
    if ($metadata) {
      $metadata->getTranslationMetadata($translation)->setSource(BIOLAND_HOME_NODE_LANGCODE);
    }
    $added[] = $langcode;
  }

  if (!$added) {
    return FALSE;
  }
  $messages[] = 'Added translation(s): ' . implode(', ', $added) . '.';
  return TRUE;
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * BL-838: create the editable home node 1000 and make it the front page.
 *
 * The helper is idempotent, so it can be re-run by hand when this update
 * logged a failure (the schema version still advances):
 * drush php:eval "\Drupal::moduleHandler()->loadInclude('bioland', 'install'); echo _bioland_ensure_home_node();"
 */
function bioland_update_9100() {
  $messages = [_bioland_ensure_home_node()];

  // Converge canonical Search API v2 config last (highest-numbered hook).
  $messages[] = _bioland_v2_update_search_and_facets_config();

  return implode(' ', array_map('strval', $messages));
}
