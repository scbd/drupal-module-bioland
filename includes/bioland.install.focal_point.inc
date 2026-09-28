<?php

/**
 * @file
 * Hero image focal point ("window selector") install functions (BL-841).
 *
 * Focal Point is a soft dependency: drupal/focal_point is suggested in
 * composer.json, not required in bioland.info.yml, so sites that have not
 * composer-required it keep working and are told so by bioland_requirements().
 */

use Drupal\bioland\BiolandFocalPoint;

/**
 * Removes a stale media.bioland_focal_point field storage definition.
 *
 * bioland_focal_point is computed, and core leaves computed base fields out of
 * EntityFieldManager::getFieldStorageDefinitions(), so it must never be
 * installed as field storage. 1.1.9's bioland_update_9084() installed it
 * anyway, which left every site that ran it with "Mismatched entity and/or
 * field definitions: The Focal point field needs to be uninstalled". This
 * drops that record from the last installed schema only: the field has no
 * table or column, so no data is touched, and bioland_entity_base_field_info()
 * keeps providing it at runtime. Idempotent.
 *
 * @return string
 *   Status message.
 */
function _bioland_remove_stale_hero_focal_point_storage() {
  $repository = \Drupal::service('entity.last_installed_schema.repository');
  $definitions = $repository->getLastInstalledFieldStorageDefinitions('media');

  if (!isset($definitions[BiolandFocalPoint::FIELD_NAME])) {
    return (string) t('No stale @field field storage definition to remove.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $repository->deleteLastInstalledFieldStorageDefinition($definitions[BiolandFocalPoint::FIELD_NAME]);
  return (string) t('Removed the stale @field field storage definition from media.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
}

/**
 * Enables focal_point when its code is present.
 *
 * Only called from bioland_update_9084(); hook_install never installs modules.
 * A failed install (e.g. crop missing from the codebase) is reported instead of
 * failing the update hook.
 *
 * @return string|null
 *   Status message, or NULL when focal_point is already enabled.
 */
function _bioland_enable_focal_point_module() {
  if (\Drupal::moduleHandler()->moduleExists('focal_point')) {
    return NULL;
  }
  if (!\Drupal::service('extension.list.module')->exists('focal_point')) {
    return (string) t('Focal Point is not available (composer require drupal/focal_point).');
  }
  try {
    \Drupal::service('module_installer')->install(['focal_point']);
  }
  catch (\Throwable $e) {
    return (string) t('Could not enable Focal Point: @error', ['@error' => $e->getMessage()]);
  }
  return (string) t('Enabled the Focal Point module.');
}

/**
 * Switches the hero media image widget to Focal Point.
 *
 * Only acts when focal_point is already enabled: points the media.hero.default
 * form display field_media_image widget at image_focal_point, and makes sure a
 * jsonapi_extras media--hero override (if one exists) does not hide
 * bioland_focal_point. Idempotent.
 *
 * @return string
 *   Status message.
 */
function _bioland_configure_hero_focal_point() {
  if (!\Drupal::moduleHandler()->moduleExists('focal_point')) {
    return (string) t('Focal Point is not enabled; skipped the hero focal point widget.');
  }

  $messages = [];
  $display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('media.hero.default');
  $component = $display ? $display->getComponent(BiolandFocalPoint::IMAGE_FIELD) : NULL;

  if (!$component) {
    $messages[] = t('Form display media.hero.default or its @field widget not found; skipped the focal point widget.', ['@field' => BiolandFocalPoint::IMAGE_FIELD]);
  }
  elseif (($component['type'] ?? '') === 'image_focal_point') {
    $messages[] = t('Hero image already uses the focal point widget.');
  }
  else {
    // Keep existing image widget settings; the focal point widget requires a
    // preview image style.
    $component['type'] = 'image_focal_point';
    $component['settings'] = [
      'preview_link' => TRUE,
      'offsets' => '50,50',
    ] + ($component['settings'] ?? []) + [
      'progress_indicator' => 'throbber',
      'preview_image_style' => 'thumbnail',
    ];
    $display->setComponent(BiolandFocalPoint::IMAGE_FIELD, $component)->save();
    $messages[] = t('Hero image now uses the focal point widget.');
  }

  $messages[] = _bioland_expose_hero_focal_point_jsonapi();

  return implode(' ', array_map('strval', $messages));
}

/**
 * Enables bioland_focal_point in a jsonapi_extras media--hero override.
 *
 * Without an override, jsonapi_extras exposes every field (computed ones
 * included) under its own name, so only an existing override is touched.
 *
 * @return string
 *   Status message.
 */
function _bioland_expose_hero_focal_point_jsonapi() {
  $entityTypeManager = \Drupal::entityTypeManager();
  if (!$entityTypeManager->hasDefinition('jsonapi_resource_config')) {
    return (string) t('jsonapi_extras not enabled; @field is exposed by core JSON:API.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $resource = $entityTypeManager->getStorage('jsonapi_resource_config')->load('media--hero');
  if (!$resource) {
    return (string) t('No media--hero JSON:API override; @field is exposed by default.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $fields = $resource->get('resourceFields') ?: [];
  $fields[BiolandFocalPoint::FIELD_NAME] = [
    'fieldName' => BiolandFocalPoint::FIELD_NAME,
    'publicName' => BiolandFocalPoint::FIELD_NAME,
    'enhancer' => ['id' => ''],
    'disabled' => FALSE,
  ];
  $resource->set('resourceFields', $fields);
  $resource->save();

  return (string) t('Exposed @field on the media--hero JSON:API resource.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
}

/*
 * =============================================================================
 * UPDATE HOOKS
 * =============================================================================
 */

/**
 * BL-841: enable the hero image focal point (window selector).
 *
 * Re-applies the canonical v2 Search API config first so a focal point
 * failure cannot skip it. It no longer installs field storage for the computed
 * bioland_focal_point field; see bioland_update_9085().
 */
function bioland_update_9084() {
  $messages = [_bioland_v2_update_search_and_facets_config()];
  $messages[] = _bioland_enable_focal_point_module();
  $messages[] = _bioland_configure_hero_focal_point();
  return implode(' ', array_map('strval', array_filter($messages)));
}

/**
 * BL-841: repair the focal point field storage and the DMSM config.
 *
 * Fixes "The Focal point field needs to be uninstalled" on sites that ran the
 * 1.1.9 bioland_update_9084(). It also seeds bioland.settings
 * dmsm_config_base_url from BIOLAND_DMSM_CONFIG_BASE_URL on sites that were
 * freshly installed before bioland_install() did so: a fresh install marks
 * bioland_update_9082() as run without executing it. Both steps are no-ops
 * where there is nothing to fix.
 *
 * As the highest-numbered hook it runs LAST on every site, so per
 * SearchApiConvergenceHookTest it also re-applies the canonical v2 Search API
 * config, first, so a failure below cannot skip it. The DMSM seed runs last
 * and fails the update (like 9082) when the deployment offers no value, so a
 * misconfigured environment surfaces at deploy time.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9085() {
  $messages = [_bioland_v2_update_search_and_facets_config()];
  $messages[] = _bioland_remove_stale_hero_focal_point_storage();
  $messages[] = _bioland_seed_dmsm_config_or_fail();
  return implode(' ', array_map('strval', array_filter($messages)));
}
