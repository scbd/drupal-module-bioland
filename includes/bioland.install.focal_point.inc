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
 * Installs the computed media.bioland_focal_point field storage definition.
 *
 * Without this the status report shows "Mismatched entity and/or field
 * definitions" on sites that had bioland installed before BL-841. Idempotent,
 * and a no-op when media is not installed.
 *
 * @return string
 *   Status message.
 */
function _bioland_install_hero_focal_point_field() {
  if (!\Drupal::entityTypeManager()->hasDefinition('media')) {
    return (string) t('Media is not installed; skipped the @field field.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $manager = \Drupal::entityDefinitionUpdateManager();
  if ($manager->getFieldStorageDefinition(BiolandFocalPoint::FIELD_NAME, 'media')) {
    return (string) t('The @field field is already installed.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
  }

  $manager->installFieldStorageDefinition(BiolandFocalPoint::FIELD_NAME, 'media', 'bioland', BiolandFocalPoint::baseFieldDefinition());
  return (string) t('Installed the @field field on media.', ['@field' => BiolandFocalPoint::FIELD_NAME]);
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
 * As the highest-numbered hook it runs LAST on every site, so per
 * SearchApiConvergenceHookTest it also re-applies the canonical v2 Search API
 * config. Convergence runs first so a focal point failure cannot skip it.
 *
 * @see \Drupal\Tests\bioland\Unit\SearchApiConvergenceHookTest
 */
function bioland_update_9084() {
  $messages = [_bioland_v2_update_search_and_facets_config()];
  $messages[] = _bioland_install_hero_focal_point_field();
  $messages[] = _bioland_enable_focal_point_module();
  $messages[] = _bioland_configure_hero_focal_point();
  return implode(' ', array_map('strval', array_filter($messages)));
}
